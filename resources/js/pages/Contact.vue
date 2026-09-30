<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Bug,
    CircleCheck,
    Clock,
    Compass,
    Handshake,
    KeyRound,
    Lightbulb,
    Mail,
    Map,
    MessageCircle,
    Phone,
    ShieldCheck,
    Sparkles,
    User,
    UserRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import IconField from '@/components/auth/IconField.vue';
import InputError from '@/components/InputError.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useRecaptchaV3 } from '@/composables/useRecaptchaV3';
import { cn } from '@/lib/utils';
import { register } from '@/routes';
import { store } from '@/routes/contact';
import { index as myMessages } from '@/routes/contact/messages';
import { index as openTripsIndex } from '@/routes/open-trips';
import { request as passwordRequest } from '@/routes/password';
import { edit as profileEdit } from '@/routes/profile';
import { create as createTrip } from '@/routes/trips';

type Topic = { value: string; label: string };

const props = defineProps<{
    topics: Topic[];
    sender: {
        name: string;
        email: string;
        phone: string | null;
        messages_count: number;
    } | null;
    recaptcha: { enabled: boolean; siteKey: string | null };
}>();

const MESSAGE_LIMIT = 5000;

const topicIcons: Record<string, Component> = {
    general: MessageCircle,
    trip_planning: Map,
    account: UserRound,
    problem: Bug,
    partnership: Handshake,
    feedback: Lightbulb,
};

const page = usePage();

const form = useForm({
    name: '',
    email: '',
    phone: props.sender?.phone ?? '',
    topic: props.topics[0]?.value ?? 'general',
    subject: '',
    message: '',
    'g-recaptcha-response': '',
});

const sentReference = ref<string | null>(null);
const captchaSubmitting = ref(false);
const isSubmitting = computed(() => form.processing || captchaSubmitting.value);

const { execute: executeRecaptcha } = useRecaptchaV3(() =>
    props.recaptcha.enabled ? props.recaptcha.siteKey : null,
);

async function submit(): Promise<void> {
    form.clearErrors('g-recaptcha-response');

    if (props.recaptcha.enabled && props.recaptcha.siteKey) {
        captchaSubmitting.value = true;

        try {
            form['g-recaptcha-response'] = await executeRecaptcha('contact');
        } catch {
            form.setError(
                'g-recaptcha-response',
                'Captcha could not be loaded. Please refresh the page and try again.',
            );

            return;
        } finally {
            captchaSubmitting.value = false;
        }
    }

    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            sentReference.value =
                (page.flash?.contactReference as string | undefined) ?? '';
            form.reset('subject', 'message');
        },
    });
}

function startOver(): void {
    sentReference.value = null;
}

const highlights = [
    {
        icon: Clock,
        title: 'Quick replies',
        text: 'We usually answer within 1–2 business days.',
    },
    {
        icon: Sparkles,
        title: 'Real travel help',
        text: 'Itineraries, road trips, open trips — ask us anything.',
    },
    {
        icon: ShieldCheck,
        title: 'Your privacy matters',
        text: 'We only use your details to get back to you.',
    },
];

const shortcuts = computed(() => [
    {
        icon: Map,
        title: 'Planning a trip?',
        text: 'Build a day-by-day itinerary in minutes.',
        cta: props.sender ? 'Plan a trip' : 'Create a free account',
        href: props.sender ? createTrip() : register(),
    },
    {
        icon: Compass,
        title: 'Looking for travel buddies?',
        text: 'Browse open trips and request to join.',
        cta: 'Discover open trips',
        href: openTripsIndex(),
    },
    {
        icon: KeyRound,
        title: props.sender ? 'Update your details' : "Can't log in?",
        text: props.sender
            ? 'Change your name, contact details or preferences.'
            : 'Reset your password in under a minute.',
        cta: props.sender ? 'Open profile settings' : 'Reset password',
        href: props.sender ? profileEdit() : passwordRequest(),
    },
]);

const textareaClass =
    'flex min-h-40 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2.5 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm';
</script>

<template>
    <Head title="Contact us">
        <meta
            name="description"
            content="Questions, ideas or trouble with a trip? Get in touch with the TripPilot team."
        />
    </Head>

    <div class="min-h-screen bg-background text-foreground">
        <!-- Hero -->
        <section class="relative isolate">
            <img
                src="/images/destination-beach.jpg"
                alt=""
                class="absolute inset-0 -z-10 size-full object-cover"
                fetchpriority="high"
            />
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-b from-slate-950/80 via-slate-950/60 to-slate-950/85"
            />
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-r from-teal-950/60 via-transparent to-indigo-950/50"
            />

            <PublicHeader variant="overlay" />

            <div
                class="mx-auto max-w-3xl px-4 pt-10 pb-44 text-center sm:px-6 lg:pt-14 lg:pb-52"
            >
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-sm font-medium text-white backdrop-blur-sm"
                >
                    <Mail class="size-4 text-teal-300" />
                    Contact us
                </span>
                <h1
                    class="mt-6 text-4xl leading-tight font-extrabold tracking-tight text-balance text-white sm:text-5xl"
                >
                    We'd love to
                    <span
                        class="bg-gradient-to-r from-teal-300 via-sky-300 to-violet-300 bg-clip-text text-transparent"
                        >hear from you</span
                    >
                </h1>
                <p
                    class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-pretty text-white/80"
                >
                    Questions about a trip, ideas for new features or trouble
                    with your account — send us a message and a real person will
                    get back to you.
                </p>
            </div>
        </section>

        <!-- Contact card -->
        <section
            class="relative z-10 mx-auto -mt-36 max-w-6xl px-4 sm:px-6 lg:px-8"
        >
            <div
                class="grid overflow-hidden rounded-3xl border border-border/60 bg-white shadow-2xl shadow-slate-900/10 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] dark:bg-slate-900 dark:shadow-black/30"
            >
                <!-- Info panel -->
                <aside
                    class="brand-gradient relative isolate overflow-hidden p-8 text-white sm:p-10"
                >
                    <div
                        class="absolute -right-20 -bottom-24 -z-10 size-72 rounded-full bg-white/10"
                    />
                    <div
                        class="absolute -right-4 -bottom-8 -z-10 size-40 rounded-full bg-white/10"
                    />

                    <h2 class="text-2xl font-bold tracking-tight">
                        Get in touch
                    </h2>
                    <p class="mt-2 text-white/80">
                        Fill in the form and our team will reply by email.
                    </p>

                    <ul class="mt-10 grid gap-7">
                        <li
                            v-for="item in highlights"
                            :key="item.title"
                            class="flex gap-4"
                        >
                            <span
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur-sm"
                            >
                                <component :is="item.icon" class="size-5" />
                            </span>
                            <div>
                                <p class="font-semibold">{{ item.title }}</p>
                                <p class="text-sm text-white/75">
                                    {{ item.text }}
                                </p>
                            </div>
                        </li>
                    </ul>

                    <div
                        v-if="sender"
                        class="mt-10 rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur-sm"
                    >
                        <p
                            class="text-xs font-semibold tracking-widest text-white/70 uppercase"
                        >
                            Sending as
                        </p>
                        <p class="mt-1 font-semibold">{{ sender.name }}</p>
                        <p class="text-sm text-white/80">{{ sender.email }}</p>
                        <Link
                            v-if="sender.messages_count > 0"
                            :href="myMessages()"
                            class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-white underline-offset-4 hover:underline"
                        >
                            View your messages ({{ sender.messages_count }})
                            <ArrowRight class="size-4" />
                        </Link>
                    </div>
                </aside>

                <!-- Form panel -->
                <div class="p-6 sm:p-10">
                    <!-- Success -->
                    <div
                        v-if="sentReference !== null"
                        class="flex h-full flex-col items-center justify-center gap-5 py-10 text-center"
                        role="status"
                    >
                        <span
                            class="flex size-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400"
                        >
                            <CircleCheck class="size-8" />
                        </span>
                        <div class="space-y-2">
                            <h2 class="text-2xl font-bold tracking-tight">
                                Message sent!
                            </h2>
                            <p class="max-w-sm text-muted-foreground">
                                Thanks for reaching out. We'll reply to your
                                email as soon as we can.
                            </p>
                        </div>
                        <p
                            v-if="sentReference"
                            class="rounded-full bg-muted px-4 py-1.5 font-mono text-sm"
                        >
                            Reference: {{ sentReference }}
                        </p>
                        <div class="flex flex-wrap justify-center gap-2">
                            <Button v-if="sender" as-child>
                                <Link :href="myMessages()">
                                    View your messages
                                </Link>
                            </Button>
                            <Button variant="outline" @click="startOver">
                                Send another message
                            </Button>
                        </div>
                    </div>

                    <form v-else class="grid gap-6" @submit.prevent="submit">
                        <fieldset class="grid gap-3">
                            <legend class="mb-3 text-sm font-medium">
                                What can we help with?
                            </legend>
                            <div
                                class="grid grid-cols-2 gap-2.5 sm:grid-cols-3"
                            >
                                <label
                                    v-for="topic in topics"
                                    :key="topic.value"
                                    :class="
                                        cn(
                                            'flex cursor-pointer flex-col items-start gap-2 rounded-xl border p-3 text-sm font-medium transition has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50',
                                            form.topic === topic.value
                                                ? 'border-primary bg-primary/5 text-foreground shadow-sm'
                                                : 'border-border text-muted-foreground hover:border-primary/40 hover:text-foreground',
                                        )
                                    "
                                >
                                    <input
                                        v-model="form.topic"
                                        type="radio"
                                        name="topic"
                                        :value="topic.value"
                                        class="sr-only"
                                    />
                                    <component
                                        :is="
                                            topicIcons[topic.value] ??
                                            MessageCircle
                                        "
                                        :class="
                                            cn(
                                                'size-5',
                                                form.topic === topic.value
                                                    ? 'text-primary'
                                                    : '',
                                            )
                                        "
                                    />
                                    {{ topic.label }}
                                </label>
                            </div>
                            <InputError :message="form.errors.topic" />
                        </fieldset>

                        <div v-if="!sender" class="grid gap-5 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="name">Your name</Label>
                                <IconField :icon="User">
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        name="name"
                                        required
                                        autocomplete="name"
                                        placeholder="Jane Doe"
                                        class="h-11 pl-10"
                                    />
                                </IconField>
                                <InputError :message="form.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="email">Email address</Label>
                                <IconField :icon="Mail">
                                    <Input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        name="email"
                                        required
                                        autocomplete="email"
                                        placeholder="you@example.com"
                                        class="h-11 pl-10"
                                    />
                                </IconField>
                                <InputError :message="form.errors.email" />
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="phone">
                                    Phone
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(optional)</span
                                    >
                                </Label>
                                <IconField :icon="Phone">
                                    <Input
                                        id="phone"
                                        v-model="form.phone"
                                        type="tel"
                                        name="phone"
                                        autocomplete="tel"
                                        placeholder="+91 98765 43210"
                                        class="h-11 pl-10"
                                    />
                                </IconField>
                                <InputError :message="form.errors.phone" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="subject">Subject</Label>
                                <Input
                                    id="subject"
                                    v-model="form.subject"
                                    name="subject"
                                    required
                                    maxlength="150"
                                    placeholder="How can we help?"
                                    class="h-11"
                                />
                                <InputError :message="form.errors.subject" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <div class="flex items-baseline justify-between">
                                <Label for="message">Message</Label>
                                <span
                                    class="text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ form.message.length }} /
                                    {{ MESSAGE_LIMIT }}
                                </span>
                            </div>
                            <textarea
                                id="message"
                                v-model="form.message"
                                name="message"
                                required
                                :maxlength="MESSAGE_LIMIT"
                                placeholder="Tell us a bit about what you need…"
                                :aria-invalid="Boolean(form.errors.message)"
                                :class="textareaClass"
                            />
                            <InputError :message="form.errors.message" />
                        </div>

                        <InputError
                            :message="form.errors['g-recaptcha-response']"
                        />

                        <div
                            class="flex flex-col-reverse items-center gap-4 sm:flex-row sm:justify-between"
                        >
                            <p class="text-xs text-muted-foreground">
                                We'll reply to
                                <span class="font-medium text-foreground">{{
                                    sender?.email ?? 'the email above'
                                }}</span
                                >.
                            </p>
                            <Button
                                type="submit"
                                size="lg"
                                class="h-11 w-full px-8 text-base shadow-md shadow-primary/20 sm:w-auto"
                                :disabled="isSubmitting"
                                data-test="contact-submit-button"
                            >
                                <Spinner v-if="isSubmitting" />
                                Send message
                                <ArrowRight
                                    v-if="!isSubmitting"
                                    class="size-4"
                                />
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <!-- Shortcuts -->
        <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="mb-8 text-center">
                <p
                    class="text-sm font-semibold tracking-widest text-primary uppercase"
                >
                    Before you write
                </p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">
                    You might find what you need here
                </h2>
            </div>
            <div class="grid gap-5 md:grid-cols-3">
                <Link
                    v-for="item in shortcuts"
                    :key="item.title"
                    :href="item.href"
                    class="card-vibrant group flex flex-col gap-3 rounded-2xl border p-6"
                >
                    <span
                        class="brand-gradient flex size-11 items-center justify-center rounded-xl text-white shadow-md"
                    >
                        <component :is="item.icon" class="size-5" />
                    </span>
                    <h3 class="font-semibold">{{ item.title }}</h3>
                    <p class="text-sm text-muted-foreground">{{ item.text }}</p>
                    <span
                        class="mt-auto inline-flex items-center gap-1.5 pt-2 text-sm font-semibold text-primary"
                    >
                        {{ item.cta }}
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-0.5"
                        />
                    </span>
                </Link>
            </div>
        </section>

        <PublicFooter />
    </div>
</template>
