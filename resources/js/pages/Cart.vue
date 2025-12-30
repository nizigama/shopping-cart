<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import cart from '@/routes/cart';
import { type BreadcrumbItem, type CartItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Minus, Plus, ShoppingCart, Trash2, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { toast } from '@/components/ui/sonner';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ref } from 'vue';

interface Props {
    items: CartItem[];
    total: string;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Shop',
        href: dashboard().url,
    },
    {
        title: 'Cart',
        href: cart.index().url,
    },
];

const updatingItemId = ref<number | null>(null);
const removingItemId = ref<number | null>(null);
const isClearing = ref(false);

const formatPrice = (price: string) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(parseFloat(price));
};

const updateQuantity = (item: CartItem, newQuantity: number) => {
    if (newQuantity < 1) return;
    updatingItemId.value = item.id;
    router.patch(cart.update(item).url, { quantity: newQuantity }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Quantity updated', {
                description: `${item.product.name} quantity set to ${newQuantity}.`,
            });
        },
        onError: () => {
            toast.error('Failed to update quantity', {
                description: 'Please try again.',
            });
        },
        onFinish: () => {
            updatingItemId.value = null;
        },
    });
};

const removeItem = (item: CartItem) => {
    removingItemId.value = item.id;
    router.delete(cart.destroy(item).url, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Item removed', {
                description: `${item.product.name} has been removed from your cart.`,
            });
        },
        onError: () => {
            toast.error('Failed to remove item', {
                description: 'Please try again.',
            });
        },
        onFinish: () => {
            removingItemId.value = null;
        },
    });
};

const clearCart = () => {
    isClearing.value = true;
    router.delete(cart.clear().url, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Cart cleared', {
                description: 'All items have been removed from your cart.',
            });
        },
        onError: () => {
            toast.error('Failed to clear cart', {
                description: 'Please try again.',
            });
        },
        onFinish: () => {
            isClearing.value = false;
        },
    });
};

const getItemTotal = (item: CartItem) => {
    return (item.quantity * parseFloat(item.product.price)).toFixed(2);
};

const isItemLoading = (itemId: number) => {
    return updatingItemId.value === itemId || removingItemId.value === itemId;
};
</script>

<template>
    <Head title="Cart" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Your Cart
                    </h1>
                    <p class="text-muted-foreground">
                        {{ items.length }} {{ items.length === 1 ? 'item' : 'items' }} in your cart
                    </p>
                </div>
                <Button
                    v-if="items.length > 0"
                    variant="destructive"
                    @click="clearCart"
                    class="gap-2"
                    :disabled="isClearing"
                >
                    <Spinner v-if="isClearing" class="size-4" />
                    <Trash2 v-else class="size-4" />
                    {{ isClearing ? 'Clearing...' : 'Clear All' }}
                </Button>
            </div>

            <!-- Cart Items -->
            <div v-if="items.length > 0" class="flex flex-col gap-6 lg:flex-row">
                <!-- Items List -->
                <div class="flex-1 space-y-4">
                    <Card
                        v-for="item in items"
                        :key="item.id"
                        class="overflow-hidden transition-opacity"
                        :class="{ 'opacity-50': isItemLoading(item.id) }"
                    >
                        <div class="flex flex-col sm:flex-row">
                            <!-- Product Image Placeholder -->
                            <div class="relative aspect-square sm:w-32 sm:h-32 bg-gradient-to-br from-muted to-muted/50 flex items-center justify-center shrink-0">
                                <div class="text-3xl font-bold text-muted-foreground/20 uppercase">
                                    {{ item.product.name.charAt(0) }}
                                </div>
                                <div
                                    v-if="isItemLoading(item.id)"
                                    class="absolute inset-0 flex items-center justify-center bg-background/50"
                                >
                                    <Spinner class="size-6" />
                                </div>
                            </div>

                            <div class="flex flex-1 flex-col p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="space-y-1">
                                        <h3 class="font-semibold text-foreground capitalize line-clamp-1">
                                            {{ item.product.name }}
                                        </h3>
                                        <p class="text-sm text-muted-foreground line-clamp-1">
                                            {{ item.product.description || 'No description' }}
                                        </p>
                                        <p class="text-sm text-muted-foreground">
                                            {{ formatPrice(item.product.price) }} each
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        @click="removeItem(item)"
                                        class="shrink-0 text-muted-foreground hover:text-destructive"
                                        :disabled="isItemLoading(item.id)"
                                    >
                                        <Spinner v-if="removingItemId === item.id" class="size-4" />
                                        <X v-else class="size-4" />
                                    </Button>
                                </div>

                                <div class="mt-4 flex items-center justify-between">
                                    <!-- Quantity Controls -->
                                    <div class="flex items-center gap-2 rounded-lg border bg-background p-1">
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            @click="updateQuantity(item, item.quantity - 1)"
                                            :disabled="item.quantity <= 1 || isItemLoading(item.id)"
                                        >
                                            <Minus class="size-4" />
                                        </Button>
                                        <span class="w-10 text-center font-medium">
                                            {{ item.quantity }}
                                        </span>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            @click="updateQuantity(item, item.quantity + 1)"
                                            :disabled="isItemLoading(item.id)"
                                        >
                                            <Plus class="size-4" />
                                        </Button>
                                    </div>

                                    <!-- Item Total -->
                                    <p class="text-lg font-bold text-primary">
                                        {{ formatPrice(getItemTotal(item)) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>

                <!-- Order Summary -->
                <div class="lg:w-80">
                    <Card class="sticky top-6">
                        <CardHeader>
                            <CardTitle>Order Summary</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Subtotal</span>
                                <span class="font-medium">{{ formatPrice(total) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Shipping</span>
                                <span class="font-medium text-green-600">Free</span>
                            </div>
                            <div class="border-t pt-4">
                                <div class="flex justify-between">
                                    <span class="text-lg font-semibold">Total</span>
                                    <span class="text-lg font-bold text-primary">{{ formatPrice(total) }}</span>
                                </div>
                            </div>
                        </CardContent>
                        <CardFooter>
                            <Button class="w-full" size="lg">
                                Proceed to Checkout
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-muted-foreground/25 p-12"
            >
                <div class="rounded-full bg-muted p-4 mb-4">
                    <ShoppingCart class="size-8 text-muted-foreground" />
                </div>
                <h3 class="text-lg font-semibold text-foreground">Your cart is empty</h3>
                <p class="text-sm text-muted-foreground mt-1 mb-4">
                    Add some products to get started
                </p>
                <Button as="a" :href="dashboard().url">
                    Continue Shopping
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
