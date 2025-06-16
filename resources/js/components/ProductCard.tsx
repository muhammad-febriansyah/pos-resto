// src/components/ProductCard.tsx

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Product } from '@/types/product';
import { Minus, Plus, ShoppingCart } from 'lucide-react'; // Import ShoppingCart icon
import React from 'react';

interface ProductCardProps {
    product: Product;
    quantityInCart: number;
    isInCart: boolean;
    isOutOfStock: boolean;
    addProductToCart: (product: Product) => void;
    updateQuantity: (productId: number, delta: number) => void;
    removeItemFromCart: (productId: number) => void;
}

const ProductCard: React.FC<ProductCardProps> = ({ product, quantityInCart, isInCart, isOutOfStock, addProductToCart, updateQuantity }) => {
    const imageUrl = product.image ? `/storage/${product.image}` : '/images/placeholder.svg';
    const hasPromo = product.promo > 0;
    const discountedPrice = hasPromo ? product.harga_jual - (product.harga_jual * product.percentage) / 100 : product.harga_jual;

    return (
        <Card
            // Consider setting a fixed height like h-[320px] on the Card if you want
            // perfectly uniform cards in the grid, regardless of content length.
            // Example: className={`... h-[320px] ${isOutOfStock ? ...}`}
            className={`group relative flex h-min cursor-pointer flex-col overflow-hidden rounded-xl border p-4 shadow-sm transition-all duration-200 hover:shadow-md ${isOutOfStock ? 'opacity-60 grayscale' : ''}`}
            onClick={() => !isOutOfStock && addProductToCart(product)}
        >
            {isOutOfStock && (
                <div className="bg-opacity-50 absolute inset-0 z-10 flex items-center justify-center bg-black/40 text-xl font-bold text-white">
                    Stok Habis
                </div>
            )}
            <img src={imageUrl} alt={product.nama_produk} className="mb-3 h-40 w-full rounded-lg object-cover" />
            <CardContent className="flex flex-1 flex-col justify-between p-0">
                <h3 className="mb-1 line-clamp-2 text-base font-semibold text-gray-800">{product.nama_produk}</h3>

                {/* Display Stock */}
                <p className="mb-2 text-sm text-gray-500">Stok: {product.stok}</p>

                {/* --- Price and Add/Quantity controls in a single row --- */}
                <div className="mt-3 flex flex-row items-center justify-between gap-2">
                    {' '}
                    {/* Added flex-row, items-center */}
                    <div className="flex flex-col">
                        {' '}
                        {/* Wrapper for price(s) */}
                        {hasPromo && (
                            <span className="text-xs text-red-500 line-through">{new Intl.NumberFormat('id-ID').format(product.harga_jual)}</span>
                        )}
                        <span className="text-lg font-bold text-green-600">{new Intl.NumberFormat('id-ID').format(discountedPrice)}</span>
                    </div>
                    {isInCart ? (
                        <div className="flex items-center space-x-1">
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8 rounded-full text-indigo-500"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    updateQuantity(product.id, -1);
                                }}
                                disabled={quantityInCart <= 1}
                            >
                                <Minus className="h-4 w-4" />
                            </Button>
                            <span className="text-base font-semibold text-gray-800">{quantityInCart}</span>
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8 rounded-full text-indigo-500"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    updateQuantity(product.id, 1);
                                }}
                                disabled={quantityInCart >= product.stok}
                            >
                                <Plus className="h-4 w-4" />
                            </Button>
                        </div>
                    ) : (
                        <Button
                            className="flex h-9 flex-shrink-0 items-center justify-center rounded-lg bg-biru px-3 py-2 text-sm font-semibold transition-colors duration-200 hover:bg-indigo-700" // Adjusted padding/height for better fit
                            onClick={(e) => {
                                e.stopPropagation();
                                addProductToCart(product);
                            }}
                            disabled={isOutOfStock}
                        >
                            <ShoppingCart className="mr-2 h-4 w-4" /> Add
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
};

export default ProductCard;
