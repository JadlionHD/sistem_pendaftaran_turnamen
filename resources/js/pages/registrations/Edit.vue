<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Gamepad2, Save, ShieldAlert } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Game {
    name: string;
    genre: string;
    team_size: number;
}

interface Tournament {
    id: number;
    title: string;
    game: Game;
}

interface Registration {
    id: number;
    tournament_id: number;
    team_name: string;
    captain_name: string;
    captain_whatsapp: string;
    captain_email: string;
    team_members: string;
    status: string;
    tournament: Tournament;
}

const props = defineProps<{
    registration: Registration;
}>();

const form = useForm({
    team_name: props.registration.team_name,
    captain_name: props.registration.captain_name,
    captain_whatsapp: props.registration.captain_whatsapp,
    captain_email: props.registration.captain_email,
    team_members: props.registration.team_members,
});

const submit = () => {
    form.put(`/registrations/${props.registration.id}`);
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pendaftaran Tim',
                href: '/registrations',
            },
            {
                title: 'Ubah Data Tim',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6 p-4 md:p-6 max-w-4xl mx-auto w-full">
        <Head :title="`Ubah: ${registration.team_name}`" />

        <div class="flex items-center gap-3">
            <Button as-child variant="ghost" size="icon">
                <Link href="/registrations">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Perbarui Data Pendaftaran Tim</h1>
                <p class="text-muted-foreground text-sm">
                    Perubahan hanya dapat dilakukan selama status pendaftaran masih dalam tahap verifikasi (pending).
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Summary Info Kiri -->
            <div class="space-y-4">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base flex items-center gap-2">
                            <Gamepad2 class="size-4 text-primary" />
                            Turnamen Terdaftar
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-xs">
                        <div>
                            <span class="text-muted-foreground block">Turnamen:</span>
                            <span class="font-semibold text-sm block">{{ registration.tournament.title }}</span>
                        </div>

                        <div>
                            <span class="text-muted-foreground block">Game:</span>
                            <Badge variant="outline" class="mt-1">
                                {{ registration.tournament.game.name }}
                            </Badge>
                        </div>

                        <div>
                            <span class="text-muted-foreground block">Format Tim:</span>
                            <span class="font-medium text-foreground">
                                {{ registration.tournament.game.team_size }} Pemain
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <div class="p-4 rounded-xl border border-blue-500/20 bg-blue-500/5 text-blue-700 dark:text-blue-400 text-xs space-y-2">
                    <div class="flex items-center gap-1.5 font-semibold">
                        <ShieldAlert class="size-4" /> Data Pre-filled
                    </div>
                    <p>
                        Nilai data sebelumnya telah dimuat otomatis ke setiap kolom isian formulir.
                    </p>
                </div>
            </div>

            <!-- Form Utama Kanan -->
            <Card class="md:col-span-2">
                <CardHeader>
                    <CardTitle>Formulir Perubahan Data Tim</CardTitle>
                    <CardDescription>
                        Perbaiki nama tim, nomor kontak kapten, atau roster susunan pemain.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-5">
                        <!-- Nama Tim -->
                        <div class="space-y-1.5">
                            <Label for="team_name">Nama Tim <span class="text-rose-500">*</span></Label>
                            <Input
                                id="team_name"
                                v-model="form.team_name"
                                :class="{ 'border-rose-500': form.errors.team_name }"
                            />
                            <p v-if="form.errors.team_name" class="text-xs text-rose-500 font-medium">
                                {{ form.errors.team_name }}
                            </p>
                        </div>

                        <!-- Data Kapten -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="captain_name">Nama Kapten Tim <span class="text-rose-500">*</span></Label>
                                <Input
                                    id="captain_name"
                                    v-model="form.captain_name"
                                    :class="{ 'border-rose-500': form.errors.captain_name }"
                                />
                                <p v-if="form.errors.captain_name" class="text-xs text-rose-500 font-medium">
                                    {{ form.errors.captain_name }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="captain_whatsapp">Nomor WhatsApp Kapten <span class="text-rose-500">*</span></Label>
                                <Input
                                    id="captain_whatsapp"
                                    v-model="form.captain_whatsapp"
                                    :class="{ 'border-rose-500': form.errors.captain_whatsapp }"
                                />
                                <p v-if="form.errors.captain_whatsapp" class="text-xs text-rose-500 font-medium">
                                    {{ form.errors.captain_whatsapp }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="captain_email">Email Aktif Kapten <span class="text-rose-500">*</span></Label>
                            <Input
                                id="captain_email"
                                type="email"
                                v-model="form.captain_email"
                                :class="{ 'border-rose-500': form.errors.captain_email }"
                            />
                            <p v-if="form.errors.captain_email" class="text-xs text-rose-500 font-medium">
                                {{ form.errors.captain_email }}
                            </p>
                        </div>

                        <!-- Daftar Anggota Tim -->
                        <div class="space-y-1.5">
                            <Label for="team_members">
                                Daftar Roster / Nama Anggota Tim <span class="text-rose-500">*</span>
                            </Label>
                            <textarea
                                id="team_members"
                                v-model="form.team_members"
                                rows="6"
                                class="w-full rounded-md border border-input bg-background p-3 text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-ring font-mono text-xs"
                                :class="{ 'border-rose-500': form.errors.team_members }"
                            />
                            <p v-if="form.errors.team_members" class="text-xs text-rose-500 font-medium">
                                {{ form.errors.team_members }}
                            </p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                            <Button as-child variant="outline">
                                <Link href="/registrations">Batal</Link>
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                <Save class="size-4 mr-2" />
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
