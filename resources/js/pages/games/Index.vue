<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Edit2, Gamepad2, Plus, Trash2, X } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Game {
    id: number;
    name: string;
    slug: string;
    genre: string;
    team_size: number;
    platform: string;
    description: string | null;
    is_active: boolean;
    tournaments_count: number;
}

const props = defineProps<{
    games: Game[];
    canManage: boolean;
}>();

const isModalOpen = ref(false);
const editingGame = ref<Game | null>(null);

const form = useForm({
    name: '',
    genre: 'MOBA',
    team_size: 5,
    platform: 'Mobile',
    description: '',
    is_active: true,
});

const openCreateModal = () => {
    editingGame.value = null;
    form.reset();
    form.clearErrors();
    form.is_active = true;
    isModalOpen.value = true;
};

const openEditModal = (game: Game) => {
    editingGame.value = game;
    form.clearErrors();
    form.name = game.name;
    form.genre = game.genre;
    form.team_size = game.team_size;
    form.platform = game.platform;
    form.description = game.description || '';
    form.is_active = game.is_active;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingGame.value = null;
    form.reset();
};

const submit = () => {
    if (editingGame.value) {
        form.put(`/games/${editingGame.value.id}`, {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post('/games', {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteGame = (game: Game) => {
    if (confirm(`Yakin ingin menghapus game "${game.name}"?`)) {
        form.delete(`/games/${game.id}`);
    }
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Master Game',
                href: '/games',
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6 p-4 md:p-6 max-w-6xl mx-auto w-full">
        <Head title="Master Game (Data Pendukung)" />

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-border pb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight flex items-center gap-2">
                    <Gamepad2 class="size-7 text-primary" />
                    Master Data Game
                </h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    Data pendukung domain: kategori game e-sport yang dapat diselenggarakan turnamennya.
                </p>
            </div>

            <Button v-if="canManage" @click="openCreateModal" class="gap-1.5">
                <Plus class="size-4" /> Tambah Game Baru
            </Button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <Card
                v-for="game in games"
                :key="game.id"
                class="hover:border-primary/50 transition-colors shadow-xs"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-2">
                        <Badge variant="outline">{{ game.genre }}</Badge>
                        <Badge :variant="game.is_active ? 'default' : 'secondary'" class="text-xs">
                            {{ game.is_active ? 'Aktif' : 'Nonaktif' }}
                        </Badge>
                    </div>
                    <CardTitle class="text-lg mt-2">{{ game.name }}</CardTitle>
                    <CardDescription class="text-xs min-h-[2.5rem] line-clamp-2">
                        {{ game.description || 'Tidak ada deskripsi.' }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="text-xs space-y-2 pt-0 border-t border-border/50 bg-muted/20 py-3">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Platform:</span>
                        <span class="font-medium text-foreground">{{ game.platform }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Format Tim:</span>
                        <span class="font-medium text-foreground">{{ game.team_size }} Pemain / Skuad</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Turnamen Terkait:</span>
                        <span class="font-semibold text-primary">{{ game.tournaments_count }} Turnamen</span>
                    </div>

                    <div v-if="canManage" class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                        <Button variant="outline" size="sm" class="h-8 text-xs gap-1" @click="openEditModal(game)">
                            <Edit2 class="size-3.5" /> Edit
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 text-xs text-rose-500 hover:bg-rose-500/10"
                            :disabled="game.tournaments_count > 0"
                            @click="deleteGame(game)"
                            :title="game.tournaments_count > 0 ? 'Tidak bisa dihapus karena masih ada turnamen' : 'Hapus Game'"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Modal Tambah/Edit Game -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        >
            <div class="bg-card border border-border rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <h3 class="font-bold text-lg">
                        {{ editingGame ? 'Edit Data Game' : 'Tambah Game Baru' }}
                    </h3>
                    <Button variant="ghost" size="icon" @click="closeModal">
                        <X class="size-4" />
                    </Button>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div class="space-y-1.5">
                        <Label for="modal_name">Nama Game <span class="text-rose-500">*</span></Label>
                        <Input
                            id="modal_name"
                            v-model="form.name"
                            placeholder="Contoh: Honor of Kings"
                            :class="{ 'border-rose-500': form.errors.name }"
                        />
                        <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="modal_genre">Genre <span class="text-rose-500">*</span></Label>
                            <Input
                                id="modal_genre"
                                v-model="form.genre"
                                placeholder="MOBA, FPS, dll."
                                :class="{ 'border-rose-500': form.errors.genre }"
                            />
                            <p v-if="form.errors.genre" class="text-xs text-rose-500">{{ form.errors.genre }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="modal_team_size">Jumlah Pemain <span class="text-rose-500">*</span></Label>
                            <Input
                                id="modal_team_size"
                                type="number"
                                min="1"
                                max="20"
                                v-model.number="form.team_size"
                                :class="{ 'border-rose-500': form.errors.team_size }"
                            />
                            <p v-if="form.errors.team_size" class="text-xs text-rose-500">{{ form.errors.team_size }}</p>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="modal_platform">Platform <span class="text-rose-500">*</span></Label>
                        <Input
                            id="modal_platform"
                            v-model="form.platform"
                            placeholder="Mobile (Android/iOS), PC, Konsol"
                            :class="{ 'border-rose-500': form.errors.platform }"
                        />
                        <p v-if="form.errors.platform" class="text-xs text-rose-500">{{ form.errors.platform }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="modal_description">Deskripsi Singkat</Label>
                        <textarea
                            id="modal_description"
                            v-model="form.description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background p-2.5 text-xs shadow-xs focus:ring-2 focus:ring-ring"
                        />
                    </div>

                    <div v-if="editingGame" class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="modal_active"
                            v-model="form.is_active"
                            class="rounded border-input text-primary"
                        />
                        <Label for="modal_active">Status Game Aktif</Label>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t border-border">
                        <Button type="button" variant="outline" size="sm" @click="closeModal">Batal</Button>
                        <Button type="submit" size="sm" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Game' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
