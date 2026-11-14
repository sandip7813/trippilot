<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Camera, Trash2, Upload } from '@lucide/vue';
import { computed } from 'vue';
import ProfileAvatarController from '@/actions/App/Http/Controllers/Settings/ProfileAvatarController';
import InputError from '@/components/InputError.vue';
import SettingsCard from '@/components/SettingsCard.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import UserAvatar from '@/components/UserAvatar.vue';
import { edit } from '@/routes/profile-picture';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile picture',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Profile picture" />

        <h1 class="sr-only">Profile picture</h1>

        <SettingsCard
            title="Profile picture"
            description="Shown on your account, your open trips, and your join requests"
            :icon="Camera"
            accent="cyan"
        >
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                <UserAvatar
                    :src="user.avatar"
                    :name="user.name"
                    class="size-28 rounded-2xl shadow-md"
                />

                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-start gap-3">
                        <Form
                            v-bind="ProfileAvatarController.update.form()"
                            v-slot="{ errors, processing }"
                            reset-on-success
                            class="flex flex-col gap-2"
                        >
                            <Button
                                type="button"
                                :disabled="processing"
                                class="brand-gradient border-0 text-white shadow-md shadow-cyan-500/25 transition hover:opacity-90"
                                as-child
                            >
                                <label class="cursor-pointer">
                                    <Spinner v-if="processing" class="size-4" />
                                    <Upload v-else class="size-4" />
                                    {{
                                        user.avatar
                                            ? 'Change picture'
                                            : 'Upload picture'
                                    }}
                                    <input
                                        type="file"
                                        name="avatar"
                                        accept="image/jpeg,image/png,image/webp"
                                        class="sr-only"
                                        data-test="avatar-input"
                                        :disabled="processing"
                                        @change="
                                            (
                                                $event.target as HTMLInputElement
                                            ).form?.requestSubmit()
                                        "
                                    />
                                </label>
                            </Button>
                            <InputError :message="errors.avatar" />
                        </Form>

                        <Form
                            v-if="user.avatar"
                            v-bind="ProfileAvatarController.destroy.form()"
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="processing"
                                data-test="remove-avatar-button"
                            >
                                <Spinner v-if="processing" class="size-4" />
                                <Trash2 v-else class="size-4" />
                                Remove
                            </Button>
                        </Form>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        JPG, PNG or WebP, up to 5 MB. It will be cropped to a
                        square.
                    </p>
                </div>
            </div>
        </SettingsCard>
    </div>
</template>
