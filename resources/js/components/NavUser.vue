<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown, LogIn, UserPlus } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import type { Team } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const { isMobile, state } = useSidebar();

const currentTeam = computed(() => page.props.currentTeam as Team | null);
</script>

<template>
    <div v-if="!user" class="flex flex-col gap-2 p-2">
        <Button as-child size="sm" class="w-full justify-start gap-2">
            <Link href="/login">
                <LogIn class="size-4" /> Masuk
            </Link>
        </Button>
        <Button as-child variant="outline" size="sm" class="w-full justify-start gap-2">
            <Link href="/register">
                <UserPlus class="size-4" /> Daftar Akun
            </Link>
        </Button>
    </div>

    <SidebarMenu v-else>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="sidebar-menu-button"
                    >
                        <UserInfo :user="user" :team="currentTeam" />
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
