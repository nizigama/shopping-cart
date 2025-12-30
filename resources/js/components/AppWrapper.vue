<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <slot />
    <Toaster position="top-right" :richColors="true" />
</template>

