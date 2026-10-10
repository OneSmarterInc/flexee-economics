<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';

const props = defineProps<{
    code: string;
    className: string;
    course: string;
    full: boolean;
    problem: string | null;
}>();

defineOptions({
    layout: {
        title: 'Join your class',
        description: 'Make your login for Halden Energy.',
    },
});
</script>

<template>
    <Head :title="`Join ${props.className}`" />

    <p class="text-center text-sm">
        <span class="font-semibold">{{ props.className }}</span>
        <span class="text-muted-foreground"> · {{ props.course }}</span>
    </p>

    <div v-if="props.problem" class="space-y-4 text-center">
        <p class="text-sm text-red-600">{{ props.problem }}</p>
    </div>
    <div v-else-if="props.full" class="space-y-4 text-center">
        <p class="text-muted-foreground text-sm">
            This class is full. Ask your instructor.
        </p>
    </div>
    <Form
        v-else
        method="post"
        :action="`/join/${props.code}`"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Your name</Label>
                <Input
                    id="name"
                    type="text"
                    name="name"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    placeholder="As it should appear to your team"
                />
                <InputError :message="errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    placeholder="you@university.edu"
                />
                <InputError :message="errors.email" />
            </div>
            <div class="grid gap-2">
                <Label for="password">Password (8 characters or more)</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                />
                <InputError :message="errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="password_confirmation">Password again</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                />
            </div>
            <Button
                type="submit"
                class="mt-2 w-full"
                :tabindex="5"
                :disabled="processing"
                data-test="join-button"
            >
                <Spinner v-if="processing" />
                Join the class
            </Button>
        </div>
        <div class="text-muted-foreground text-center text-sm">
            Already have a login?
            <TextLink :href="login()" :tabindex="6">Log in</TextLink>
            first, then open this link again.
        </div>
    </Form>
</template>
