// src/Components/ProductGrid.tsx

import ProductCard from '@/components/ProductCard';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Kategori } from '@/types/kategori'; // Make sure this import is correct
import { Product } from '@/types/product'; // Make sure this import is correct
import { Package, Tag } from 'lucide-react';
import React from 'react';

interface ProductGridProps {
    products: Product[];
    kategoris: Kategori[];
    searchTerm: string;
    setSearchTerm: (term: string) => void;
    selectedCategoryId: number | null;
    setSelectedCategoryId: (id: number | null) => void;
    filteredProducts: Product[];
    addProductToCart: (product: Product) => void;
    updateQuantity: (productId: number, delta: number) => void;
    removeItemFromCart: (productId: number) => void;
    getProductQuantityInCart: (productId: number) => number;
}

const ProductGrid: React.FC<ProductGridProps> = ({
    kategoris,
    searchTerm,
    setSearchTerm,
    selectedCategoryId,
    setSelectedCategoryId,
    filteredProducts,
    addProductToCart,
    updateQuantity,
    removeItemFromCart,
    getProductQuantityInCart,
}) => {
    return (
        <Card className="flex flex-col rounded-2xl bg-white p-6 shadow-md lg:col-span-2">
            <CardHeader className="px-0 pt-0 pb-4">
                <CardTitle className="flex items-center text-2xl font-bold text-gray-800">
                    <Tag className="mr-3 h-7 w-7 text-biru" /> Pilih Produk
                </CardTitle>
                <Input
                    placeholder="Cari produk..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="mt-4 rounded-lg border-gray-300 p-2 focus:border-indigo-500 focus:ring-indigo-500"
                />
                {/* Filter Kategori (Toggle Buttons) */}
                <div className="mt-4 flex flex-wrap gap-2">
                    <Button
                        variant={selectedCategoryId === null ? 'default' : 'outline'}
                        onClick={() => setSelectedCategoryId(null)}
                        className={`flex-shrink-0 rounded-lg px-4 py-2 text-sm transition-colors duration-200 ${selectedCategoryId === null ? 'bg-biru text-white hover:bg-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}
                    >
                        <Package className="mr-2 h-4 w-4" /> Semua Produk
                    </Button>
                    {kategoris.map((kategori) => {
                        return (
                            <Button
                                key={kategori.id}
                                variant={selectedCategoryId === kategori.id ? 'default' : 'outline'}
                                onClick={() => setSelectedCategoryId(kategori.id)}
                                className={`flex-shrink-0 rounded-lg px-4 py-2 text-sm transition-colors duration-200 ${selectedCategoryId === kategori.id ? 'bg-biru text-white hover:bg-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}
                            >
                                <img src={`/storage/${kategori.icon}`} className="mr-2 h-6 w-6 object-cover" alt="" />
                                {kategori.kategori}
                            </Button>
                        );
                    })}
                </div>
            </CardHeader>
            <div className="custom-scrollbar grid flex-1 grid-cols-1 gap-6 overflow-y-auto px-0 pt-4 pb-0 md:grid-cols-3">
                {filteredProducts.length === 0 ? (
                    <p className="col-span-full py-10 text-center text-gray-500">Produk tidak ditemukan.</p>
                ) : (
                    filteredProducts.map((product) => {
                        const quantityInCart = getProductQuantityInCart(product.id);
                        const isInCart = quantityInCart > 0;
                        const isOutOfStock = product.stok === 0;

                        return (
                            <ProductCard
                                key={product.id}
                                product={product} // Pass the full product object
                                quantityInCart={quantityInCart}
                                isInCart={isInCart}
                                isOutOfStock={isOutOfStock}
                                addProductToCart={addProductToCart}
                                updateQuantity={updateQuantity}
                                removeItemFromCart={removeItemFromCart}
                            />
                        );
                    })
                )}
            </div>
        </Card>
    );
};

export default ProductGrid;
