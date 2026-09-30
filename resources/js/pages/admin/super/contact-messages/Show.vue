<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarClock,
    CircleCheck,
    Mail,
    Phone,
    RotateCcw,
    Send,
    Tag,
    UserRound,
} from '@lucide/vue';
import { ref } from 'vue';
import ContactStatusBadge from '@/components/admin/ContactStatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatRelativeTime } from '@/lib/dates';
import {
    index,
    reply,
    status as updateStatus,
} from '@/routes/admin/super/contact-messages';
import type { AdminContactMessage } from '@/types/admin';

const props = defineProps<{
    contactMessage: AdminContactMessage;
}>();

const closeAfterReply = ref(false);

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function firstName(): string {
    return props.contactMessage.name.split(/\s+/)[0] ?? '';
}

const textareaClass =
    'flex min-h-44 w-full resize-y rounded-md border border-input bg-background px-3 py-2.5 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <Head :title="`${contactMessage.subject} · Contact messages`" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <Button variant="ghost" size="sm" class="-ml-2" as-child>
                <Link :href="index()">
                    <ArrowLeft class="mr-1 size-4" />
                    All messages
                </Link>
            </Button>
        </div>

        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="min-w-0 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <ContactStatusBadge
                        :status="contactMessage.status"
                        :label="contactMessage.status_label"
                    />
                    <span class="font-mono text-xs text-muted-foreground">
                        {{ contactMessage.reference }}
                    </span>
                </div>
                <h1
                    class="text-2xl font-bold tracking-tight break-words md:text-3xl"
                >
                    {{ contactMessage.subject }}
                </h1>
            </div>

            <Form
                v-bind="updateStatus.form(contactMessage.id)"
                :transform="
                    () => ({
                        status:
                            contactMessage.status === 'closed'
                                ? 'new'
                                : 'closed',
                    })
                "
                v-slot="{ processing }"
            >
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="processing"
                    class="shrink-0"
                >
                    <Spinner v-if="processing" />
                    <template v-else-if="contactMessage.status === 'closed'">
                        <RotateCcw class="size-4" />
                        Reopen
                    </template>
                    <template v-else>
                        <CircleCheck class="size-4" />
                        Mark as closed
                    </template>
                </Button>
            </Form>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
            <!-- Conversation -->
            <div class="flex min-w-0 flex-col gap-4">
                <Card class="overflow-hidden">
                    <CardContent class="space-y-4 pt-6">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted font-semibold"
                            >
                                {{ contactMessage.name[0]?.toUpperCase() }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate font-semibold">
                                    {{ contactMessage.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        formatDateTime(
                                            contactMessage.created_at,
                                        )
                                    }}
                                    ·
                                    {{
                                        formatRelativeTime(
                                            contactMessage.created_at,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <p
                            class="text-[15px] leading-relaxed break-words whitespace-pre-line"
                        >
                            {{ contactMessage.message }}
                        </p>
                    </CardContent>
                </Card>

                <div
                    v-for="item in contactMessage.replies"
                    :key="item.id"
                    class="ml-4 rounded-2xl border border-primary/20 bg-primary/5 p-5 sm:ml-10"
                >
                    <div class="mb-3 flex items-center gap-3">
                        <span
                            class="brand-gradient flex size-9 shrink-0 items-center justify-center rounded-full text-white"
                        >
                            <Send class="size-4" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">
                                {{ item.author_name }}
                                <span class="font-normal text-muted-foreground"
                                    >replied by email</span
                                >
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDateTime(item.created_at) }}
                            </p>
                        </div>
                    </div>
                    <p
                        class="text-sm leading-relaxed break-words whitespace-pre-line"
                    >
                        {{ item.body }}
                    </p>
                </div>

                <!-- Composer -->
                <Card class="card-vibrant overflow-hidden">
                    <div class="brand-gradient h-1" />
                    <CardContent class="pt-6">
                        <Form
                            v-bind="reply.form(contactMessage.id)"
                            :transform="
                                (data) => ({ ...data, close: closeAfterReply })
                            "
                            reset-on-success
                            v-slot="{ errors, processing }"
                            class="grid gap-4"
                        >
                            <div class="grid gap-2">
                                <Label for="body">
                                    Reply to {{ contactMessage.name }}
                                </Label>
                                <p class="text-xs text-muted-foreground">
                                    Sent to
                                    <span class="font-medium text-foreground">{{
                                        contactMessage.email
                                    }}</span>
                                    with their original message quoted below
                                    your reply.
                                </p>
                                <textarea
                                    id="body"
                                    name="body"
                                    required
                                    :placeholder="`Hi ${firstName()}, thanks for getting in touch…`"
                                    :class="textareaClass"
                                />
                                <InputError :message="errors.body" />
                            </div>

                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <Label
                                    for="close"
                                    class="flex items-center gap-2.5 font-normal text-muted-foreground"
                                >
                                    <Checkbox
                                        id="close"
                                        v-model="closeAfterReply"
                                    />
                                    Close the conversation after sending
                                </Label>
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    data-test="send-reply-button"
                                >
                                    <Spinner v-if="processing" />
                                    <Send v-else class="size-4" />
                                    Send reply by email
                                </Button>
                            </div>
                        </Form>
                    </CardContent>
                </Card>
            </div>

            <!-- Sender details -->
            <aside class="flex flex-col gap-4">
                <Card>
                    <CardContent class="space-y-4 pt-6">
                        <p
                            class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                        >
                            Sender
                        </p>
                        <dl class="grid gap-4 text-sm">
                            <div class="flex gap-3">
                                <UserRound
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                />
                                <div class="min-w-0">
                                    <dt class="sr-only">Name</dt>
                                    <dd class="font-medium break-words">
                                        {{ contactMessage.name }}
                                    </dd>
                                    <dd class="text-xs text-muted-foreground">
                                        {{
                                            contactMessage.is_registered
                                                ? 'Registered user'
                                                : 'Guest'
                                        }}
                                    </dd>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <Mail
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                />
                                <div class="min-w-0">
                                    <dt class="sr-only">Email</dt>
                                    <dd class="break-all">
                                        <a
                                            :href="`mailto:${contactMessage.email}`"
                                            class="text-primary hover:underline"
                                            >{{ contactMessage.email }}</a
                                        >
                                    </dd>
                                </div>
                            </div>
                            <div v-if="contactMessage.phone" class="flex gap-3">
                                <Phone
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                />
                                <div>
                                    <dt class="sr-only">Phone</dt>
                                    <dd>
                                        <a
                                            :href="`tel:${contactMessage.phone}`"
                                            class="hover:underline"
                                            >{{ contactMessage.phone }}</a
                                        >
                                    </dd>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <Tag
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                />
                                <div>
                                    <dt class="sr-only">Topic</dt>
                                    <dd>{{ contactMessage.topic_label }}</dd>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <CalendarClock
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                />
                                <div>
                                    <dt class="sr-only">Received</dt>
                                    <dd>
                                        {{
                                            formatDateTime(
                                                contactMessage.created_at,
                                            )
                                        }}
                                    </dd>
                                    <dd
                                        v-if="contactMessage.replied_at"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Last reply
                                        {{
                                            formatRelativeTime(
                                                contactMessage.replied_at,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </aside>
        </div>
    </div>
</template>
