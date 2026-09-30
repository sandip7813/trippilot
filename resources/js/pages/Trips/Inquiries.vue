<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, MessageCircle } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { show as showTrip } from '@/routes/trips';
import { reply as replyToInquiry } from '@/routes/trips/inquiries';

type Message = { from_user_id: number; body: string; created_at: string };

type Inquiry = {
    id: string;
    subject: string;
    sender_name: string;
    messages: Message[];
    updated_at: string | null;
};

const { trip, inquiries } = defineProps<{
    trip: { id: string; title: string };
    inquiries: Inquiry[];
}>();
</script>

<template>
    <Head :title="`Inquiries — ${trip.title}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Inquiries"
            :description="`Messages from travelers interested in ${trip.title}`"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="showTrip(trip.id)">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to trip
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="inquiries.length === 0"
            :icon="MessageCircle"
            title="No inquiries yet"
            description="When someone contacts you about this trip, their message shows up here."
        />

        <Card
            v-for="inquiry in inquiries"
            :key="inquiry.id"
            class="card-vibrant overflow-hidden"
        >
            <div class="brand-gradient h-1" />
            <CardContent class="space-y-4 pt-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">{{ inquiry.subject }}</h2>
                    <span class="text-xs text-muted-foreground">{{
                        inquiry.sender_name
                    }}</span>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="(message, index) in inquiry.messages"
                        :key="index"
                        class="rounded-lg border bg-muted/30 p-3 text-sm"
                    >
                        <p class="whitespace-pre-wrap">{{ message.body }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ new Date(message.created_at).toLocaleString() }}
                        </p>
                    </div>
                </div>

                <Form
                    v-bind="replyToInquiry.form([trip.id, inquiry.id])"
                    v-slot="{ errors, processing, recentlySuccessful }"
                    class="space-y-2"
                >
                    <textarea
                        name="body"
                        rows="2"
                        required
                        placeholder="Write a reply..."
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="errors.body" />
                    <div class="flex items-center gap-3">
                        <Button type="submit" size="sm" :disabled="processing">
                            <Spinner v-if="processing" class="mr-2" />
                            Reply
                        </Button>
                        <span
                            v-if="recentlySuccessful"
                            class="text-sm text-muted-foreground"
                            >Sent.</span
                        >
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
