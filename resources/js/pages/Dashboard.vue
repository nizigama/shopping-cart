<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import cart from '@/routes/cart';
import { type BreadcrumbItem, type Product, type PaginatedData } from '@/types';
import { Head, router, Link } from '@inertiajs/vue3';
import { ShoppingCart, Search, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { toast } from '@/components/ui/sonner';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';

interface Props {
    products: PaginatedData<Product>;
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Shop',
        href: dashboard().url,
    },
];

const loadingProductId = ref<number | null>(null);
const searchQuery = ref(props.filters.search);
const isSearching = ref(false);

const performSearch = useDebounceFn(() => {
    isSearching.value = true;
    router.get(
        dashboard().url,
        { search: searchQuery.value || undefined },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isSearching.value = false;
            },
        }
    );
}, 300);

watch(searchQuery, () => {
    performSearch();
});

const addToCart = (product: Product) => {
    if (isOutOfStock(product)) return;

    loadingProductId.value = product.id;
    router.post(cart.store(product).url, {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Added to cart', {
                description: `${product.name} has been added to your cart.`,
            });
        },
        onError: (errors) => {
            const message = errors.stock || 'Please try again.';
            toast.error('Failed to add to cart', {
                description: message,
            });
        },
        onFinish: () => {
            loadingProductId.value = null;
        },
    });
};

const formatPrice = (price: string) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(parseFloat(price));
};

const goToPage = (url: string | null) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const getStockQuantity = (product: Product) => {
    return product.stock?.quantity ?? 0;
};

const isOutOfStock = (product: Product) => {
    return getStockQuantity(product) < 1;
};
</script>

<template>
    <Head title="Shop" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Products
                    </h1>
                    <p class="text-muted-foreground">
                        Browse our collection and add items to your cart
                    </p>
                </div>

                <!-- Search -->
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search products..."
                        class="pl-9 pr-9"
                    />
                    <Spinner
                        v-if="isSearching"
                        class="absolute right-3 top-1/2 size-4 -translate-y-1/2"
                    />
                </div>
            </div>

            <!-- Results info -->
            <div v-if="products.total > 0" class="text-sm text-muted-foreground">
                Showing {{ products.from }} to {{ products.to }} of {{ products.total }} products
            </div>

            <!-- Products Grid -->
            <div
                v-if="products.data.length > 0"
                class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <Card
                    v-for="product in products.data"
                    :key="product.id"
                    class="group flex flex-col overflow-hidden transition-all duration-200 hover:shadow-lg hover:border-primary/30"
                    :class="{ 'opacity-75': isOutOfStock(product) }"
                >
                    <!-- Product Image Placeholder -->
                    <div class="relative aspect-square bg-gradient-to-br from-muted to-muted/50 flex items-center justify-center">
                        <div class="text-6xl font-bold text-muted-foreground/20 uppercase">
                            {{ product.name.charAt(0) }}
                        </div>
                        <!-- Out of Stock Badge -->
                        <div
                            v-if="isOutOfStock(product)"
                            class="absolute inset-0 flex items-center justify-center bg-background/80"
                        >
                            <span class="rounded-full bg-destructive px-3 py-1 text-sm font-medium text-destructive-foreground">
                                Out of Stock
                            </span>
                        </div>
                    </div>

                    <CardHeader class="pb-2">
                        <CardTitle class="line-clamp-1 text-lg capitalize">
                            {{ product.name }}
                        </CardTitle>
                        <CardDescription class="line-clamp-2 min-h-[2.5rem]">
                            {{ product.description || 'No description available' }}
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex-1">
                        <p class="text-2xl font-bold text-primary">
                            {{ formatPrice(product.price) }}
                        </p>
                        <p
                            class="mt-1 text-sm"
                            :class="isOutOfStock(product) ? 'text-destructive' : 'text-muted-foreground'"
                        >
                            {{ isOutOfStock(product) ? 'Out of stock' : `${getStockQuantity(product)} in stock` }}
                        </p>
                    </CardContent>

                    <CardFooter class="pt-0">
                        <Button
                            @click="addToCart(product)"
                            class="w-full gap-2"
                            :disabled="loadingProductId === product.id || isOutOfStock(product)"
                        >
                            <Spinner v-if="loadingProductId === product.id" class="size-4" />
                            <ShoppingCart v-else class="size-4" />
                            <template v-if="loadingProductId === product.id">Adding...</template>
                            <template v-else-if="isOutOfStock(product)">Out of Stock</template>
                            <template v-else>Add to Cart</template>
                        </Button>
                    </CardFooter>
                </Card>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-muted-foreground/25 p-12"
            >
                <div class="rounded-full bg-muted p-4 mb-4">
                    <ShoppingCart class="size-8 text-muted-foreground" />
                </div>
                <h3 class="text-lg font-semibold text-foreground">
                    {{ searchQuery ? 'No products found' : 'No products available' }}
                </h3>
                <p class="text-sm text-muted-foreground mt-1">
                    {{ searchQuery ? 'Try a different search term' : 'Check back later for new arrivals' }}
                </p>
                <Button
                    v-if="searchQuery"
                    variant="outline"
                    class="mt-4"
                    @click="searchQuery = ''"
                >
                    Clear search
                </Button>
            </div>

            <!-- Pagination -->
            <div
                v-if="products.last_page > 1"
                class="flex items-center justify-center gap-2"
            >
                <Button
                    variant="outline"
                    size="icon"
                    :disabled="!products.prev_page_url"
                    @click="goToPage(products.prev_page_url)"
                >
                    <ChevronLeft class="size-4" />
                </Button>

                <div class="flex items-center gap-1">
                    <template v-for="link in products.links" :key="link.label">
                        <Button
                            v-if="!link.label.includes('Previous') && !link.label.includes('Next')"
                            :variant="link.active ? 'default' : 'outline'"
                            size="icon"
                            :disabled="!link.url || link.active"
                            @click="goToPage(link.url)"
                            class="min-w-9"
                        >
                            {{ link.label }}
                        </Button>
                    </template>
                </div>

                <Button
                    variant="outline"
                    size="icon"
                    :disabled="!products.next_page_url"
                    @click="goToPage(products.next_page_url)"
                >
                    <ChevronRight class="size-4" />
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
