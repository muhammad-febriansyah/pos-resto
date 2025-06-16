// src/Components/OrderSummary/CartTable.tsx

import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { CartItem } from '@/types'; // Import CartItem type
import { Minus, Plus, ShoppingCart, X } from 'lucide-react';
import React from 'react';

interface CartTableProps {
    cartItems: CartItem[];
    updateQuantity: (productId: number, delta: number) => void;
    removeItemFromCart: (productId: number) => void;
}

const CartTable: React.FC<CartTableProps> = ({ cartItems, updateQuantity, removeItemFromCart }) => {
    return (
        <div>
            <h3 className="mb-3 flex items-center text-lg font-semibold text-gray-800">
                <ShoppingCart className="mr-2 h-5 w-5 text-gray-500" /> Item Keranjang
            </h3>
            <div className="custom-scrollbar max-h-48 overflow-y-auto rounded-lg border border-gray-200 shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-gray-50">
                            <TableHead className="w-[40%] text-gray-600">Produk</TableHead>
                            <TableHead className="w-[20%] text-center text-gray-600">Qty</TableHead>
                            <TableHead className="w-[30%] text-right text-gray-600">Subtotal</TableHead>
                            <TableHead className="w-[10%]"></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {cartItems.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={4} className="py-4 text-center text-gray-500">
                                    Keranjang kosong.
                                </TableCell>
                            </TableRow>
                        ) : (
                            cartItems.map((item) => (
                                <TableRow key={item.id} className="hover:bg-gray-50">
                                    <TableCell className="pr-1 text-sm font-medium text-gray-700">
                                        {item.nama_produk} <br />
                                        <span className="text-xs text-gray-500">{new Intl.NumberFormat('id-ID').format(item.harga_jual || 0)}</span>
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <div className="flex items-center justify-center space-x-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-7 w-7 rounded-full text-indigo-500 hover:bg-indigo-50 hover:text-biru"
                                                onClick={() => updateQuantity(item.id, -1)}
                                                disabled={item.qty <= 1}
                                            >
                                                <Minus className="h-4 w-4" />
                                            </Button>
                                            <span className="text-sm font-semibold text-gray-800">{item.qty}</span>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-7 w-7 rounded-full text-indigo-500 hover:bg-indigo-50 hover:text-biru"
                                                onClick={() => updateQuantity(item.id, 1)}
                                                disabled={item.qty >= item.stok}
                                            >
                                                <Plus className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right text-sm font-semibold text-gray-800">
                                        {new Intl.NumberFormat('id-ID').format(item.qty * (item.harga_jual || 0))}
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-7 w-7 rounded-full text-red-500 hover:bg-red-50 hover:text-red-600"
                                            onClick={() => removeItemFromCart(item.id)}
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
};

export default CartTable;
