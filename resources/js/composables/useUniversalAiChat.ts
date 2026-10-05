import { ref, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import MarkdownIt from 'markdown-it';

export interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
    thinking?: string;
    isThinkingExpanded?: boolean;
    isTokenLimit?: boolean;
}

// Module-level reactive state to maintain conversation across Inertia page navigation
const isOpen = ref(false);
const chatMessages = ref<ChatMessage[]>([]);
const inputQuery = ref('');
const isStreaming = ref(false);
const compactContext = ref<string>('');
const isCompacting = ref(false);
const isResetting = ref(false);
const isTokenLimitReached = ref(false);

const md = new MarkdownIt({
    html: false,
    breaks: true,
    linkify: true,
    typographer: false,
});

const defaultLinkRender =
    md.renderer.rules.link_open ||
    function (tokens, idx, options, _env, self) {
        return self.renderToken(tokens, idx, options);
    };

md.renderer.rules.link_open = function (tokens, idx, options, env, self) {
    tokens[idx].attrSet('target', '_blank');
    tokens[idx].attrSet('rel', 'noopener noreferrer');
    return defaultLinkRender(tokens, idx, options, env, self);
};

export const renderMarkdown = (content: string): string => {
    if (!content) return '';
    return md.render(content);
};

export function useUniversalAiChat() {
    const page = usePage();

    const getCurrentTournament = () => {
        const props = page.props as any;
        if (props.tournament && typeof props.tournament === 'object' && props.tournament.id) {
            return {
                id: props.tournament.id as number,
                title: props.tournament.title as string,
                gameName: props.tournament.game?.name as string | undefined,
            };
        }
        return null;
    };

    const toggleChat = () => {
        isOpen.value = !isOpen.value;
    };

    const openChat = (prefillMessage?: string) => {
        isOpen.value = true;
        if (prefillMessage) {
            sendQuery(prefillMessage);
        }
    };

    const closeChat = () => {
        isOpen.value = false;
    };

    const sendQuery = async (customText?: string, scrollCallback?: () => void) => {
        const text = (customText || inputQuery.value).trim();
        if (!text || isStreaming.value || isTokenLimitReached.value) return;

        inputQuery.value = '';
        chatMessages.value.push({ role: 'user', content: text });

        const assistantIndex = chatMessages.value.length;
        chatMessages.value.push({
            role: 'assistant',
            content: '',
            thinking: '',
            isThinkingExpanded: false,
        });
        isStreaming.value = true;
        if (scrollCallback) scrollCallback();

        const currentTournament = getCurrentTournament();

        try {
            const response = await fetch('/ai-chat/stream', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                },
                body: JSON.stringify({
                    message: text,
                    tournament_id: currentTournament ? currentTournament.id : undefined,
                    history: chatMessages.value.slice(0, -2).slice(-6),
                    compact_context: compactContext.value || undefined,
                }),
            });

            if (!response.ok || !response.body) {
                chatMessages.value[assistantIndex].content = 'Terjadi kendala saat menghubungi AI Agent.';
                isStreaming.value = false;
                return;
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';

                for (const line of lines) {
                    const trimmed = line.trim();
                    if (!trimmed || !trimmed.startsWith('data:')) continue;

                    const payloadText = trimmed.slice(5).trim();
                    if (payloadText === '[DONE]') {
                        break;
                    }

                    try {
                        const parsed = JSON.parse(payloadText);
                        if (parsed.token_limit_reached) {
                            isTokenLimitReached.value = true;
                            chatMessages.value[assistantIndex].isTokenLimit = true;
                        }

                        const deltaThinking = parsed.choices?.[0]?.delta?.reasoning_content;
                        const deltaContent = parsed.choices?.[0]?.delta?.content;

                        if (deltaThinking) {
                            chatMessages.value[assistantIndex].thinking =
                                (chatMessages.value[assistantIndex].thinking || '') + deltaThinking;
                            if (scrollCallback) scrollCallback();
                        }

                        if (deltaContent) {
                            chatMessages.value[assistantIndex].content += deltaContent;
                            if (scrollCallback) scrollCallback();
                        }
                    } catch {
                        // Partial JSON chunk
                    }
                }
            }
        } catch (e: any) {
            chatMessages.value[assistantIndex].content =
                'Gagal memproses stream: ' + (e.message || 'Koneksi terputus.');
        } finally {
            isStreaming.value = false;
            const currentMsg = chatMessages.value[assistantIndex];
            if (currentMsg && currentMsg.content && currentMsg.content.includes('<think>')) {
                const thinkMatch = currentMsg.content.match(/<think>([\s\S]*?)<\/think>/);
                if (thinkMatch) {
                    currentMsg.thinking =
                        (currentMsg.thinking ? currentMsg.thinking + '\n' : '') + thinkMatch[1].trim();
                    currentMsg.content = currentMsg.content.replace(/<think>[\s\S]*?<\/think>/, '').trim();
                }
            }
            if (scrollCallback) scrollCallback();
        }
    };

    const resetChat = async () => {
        isResetting.value = true;
        try {
            await fetch('/ai-chat/reset', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
            });
        } catch {
            // Offline fallback
        } finally {
            isResetting.value = false;
            chatMessages.value = [];
            isTokenLimitReached.value = false;
        }
    };

    const compactChat = async () => {
        if (chatMessages.value.length === 0 || isCompacting.value) return;

        isCompacting.value = true;
        try {
            const response = await fetch('/ai-chat/compact', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    history: chatMessages.value.map((m) => ({ role: m.role, content: m.content })),
                    previous_compact: compactContext.value || undefined,
                }),
            });

            if (response.ok) {
                const data = await response.json();
                if (data.compacted_context) {
                    compactContext.value = data.compacted_context;
                    chatMessages.value = [];
                    isTokenLimitReached.value = false;
                }
            }
        } catch {
            const summaries = chatMessages.value
                .filter((m) => m.role === 'user')
                .map((m) => m.content.slice(0, 50))
                .join('; ');
            compactContext.value = `Percakapan sebelumnya membahas: ${summaries}`;
            chatMessages.value = [];
            isTokenLimitReached.value = false;
        } finally {
            isCompacting.value = false;
        }
    };

    const clearCompactContext = () => {
        compactContext.value = '';
    };

    return {
        isOpen,
        chatMessages,
        inputQuery,
        isStreaming,
        compactContext,
        isCompacting,
        isResetting,
        isTokenLimitReached,
        getCurrentTournament,
        toggleChat,
        openChat,
        closeChat,
        sendQuery,
        resetChat,
        compactChat,
        clearCompactContext,
    };
}
