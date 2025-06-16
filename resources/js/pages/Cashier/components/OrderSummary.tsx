// src/Components/OrderSummary/OrderSummary.tsx

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CartItem } from '@/types';
import { Meja } from '@/types/meja';
import { Setting } from '@/types/setting';
import { User } from '@/types/user';
import { BaggageClaim, Loader2, NotebookPen, ReceiptText, Table2, Users, UtensilsCrossed } from 'lucide-react';
import React from 'react';
import CartTable from './CartTable';
import PaymentSection from './PaymentSection';

interface OrderSummaryProps {
    customers: User[];
    mejas: Meja[];
    settings: Setting;
    cartItems: CartItem[];
    selectedCustomerId: string | null;
    setSelectedCustomerId: (id: string | null) => void;
    transactionType: 'dine_in' | 'take_away';
    setTransactionType: (type: 'dine_in' | 'take_away') => void;
    selectedMejaId: string | null;
    setSelectedMejaId: (id: string | null) => void;
    paymentMethod: 'cash' | 'duitku';
    setPaymentMethod: (method: 'cash' | 'duitku') => void;
    amountPaid: number;
    setAmountPaid: (amount: number) => void;
    subTotal: number;
    ppnAmount: number;
    biayaLayanan: number;
    totalAmount: number;
    change: number;
    isLoading: boolean;
    updateQuantity: (productId: number, delta: number) => void;
    removeItemFromCart: (productId: number) => void;
    handleProcessPayment: () => void;
    handleCancelTransaction: () => void;
}

const OrderSummary: React.FC<OrderSummaryProps> = ({
    customers,
    mejas,
    settings,
    cartItems,
    selectedCustomerId,
    setSelectedCustomerId,
    transactionType,
    setTransactionType,
    selectedMejaId,
    setSelectedMejaId,
    paymentMethod,
    setPaymentMethod,
    amountPaid,
    setAmountPaid,
    subTotal,
    ppnAmount,
    biayaLayanan,
    totalAmount,
    change,
    isLoading,
    updateQuantity,
    removeItemFromCart,
    handleProcessPayment,
    handleCancelTransaction,
}) => {
    return (
        <Card className="flex h-min flex-col rounded-2xl bg-white p-6 shadow-md lg:col-span-1">
            <CardHeader className="px-0 pt-0 pb-4">
                <CardTitle className="flex items-center text-2xl font-bold text-gray-800">
                    <NotebookPen className="mr-3 h-7 w-7 text-biru" /> Daftar Pesanan
                </CardTitle>
            </CardHeader>
            <CardContent className="custom-scrollbar flex-1 space-y-6 overflow-y-auto px-0 pt-4 pb-0">
                {/* Pilih Pelanggan */}
                <div>
                    <Label htmlFor="customer-select" className="mb-2 flex items-center text-sm font-medium text-gray-700">
                        <Users className="mr-2 h-4 w-4 text-gray-500" /> Pelanggan
                    </Label>
                    <Select
                        required
                        onValueChange={(value) => setSelectedCustomerId(value === 'guest_option' ? null : value)}
                        value={selectedCustomerId || 'guest_option'}
                    >
                        <SelectTrigger
                            id="customer-select"
                            className="h-10 w-full rounded-lg border-gray-300 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <SelectValue placeholder="Pilih Pelanggan (Opsional)" />
                        </SelectTrigger>
                        <SelectContent className="max-h-60 overflow-y-auto rounded-lg">
                            <SelectItem value="guest_option" className="px-3 py-2 text-base">
                                Pilih
                            </SelectItem>
                            {customers.map((customer) => (
                                <SelectItem key={customer.id} value={String(customer.id)} className="px-3 py-2 text-base">
                                    {customer.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Tipe Transaksi (Toggle Buttons) */}
                <div>
                    <Label className="mb-2 flex items-center text-sm font-medium text-gray-700">
                        <ReceiptText className="mr-2 h-4 w-4 text-gray-500" /> Tipe Transaksi
                    </Label>
                    <div className="grid grid-cols-2 gap-2">
                        <Button
                            variant={transactionType === 'dine_in' ? 'default' : 'outline'}
                            onClick={() => setTransactionType('dine_in')}
                            className={`h-10 flex-1 rounded-lg text-base transition-colors duration-200 ${transactionType === 'dine_in' ? 'bg-biru text-white shadow-sm hover:bg-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}
                        >
                            <UtensilsCrossed className="mr-2 h-4 w-4" /> Dine In
                        </Button>
                        <Button
                            variant={transactionType === 'take_away' ? 'default' : 'outline'}
                            onClick={() => setTransactionType('take_away')}
                            className={`h-10 flex-1 rounded-lg text-base transition-colors duration-200 ${transactionType === 'take_away' ? 'bg-biru text-white shadow-sm hover:bg-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}
                        >
                            <BaggageClaim className="mr-2 h-4 w-4" /> Take Away
                        </Button>
                    </div>
                </div>

                {/* Pilih Meja (Hanya muncul jika tipe transaksi 'Dine In') */}
                {transactionType === 'dine_in' && (
                    <div>
                        <Label htmlFor="meja-select" className="mb-2 flex items-center text-sm font-medium text-gray-700">
                            <Table2 className="mr-2 h-4 w-4 text-gray-500" /> Meja
                        </Label>
                        <Select
                            onValueChange={(value) => setSelectedMejaId(value === 'no_table_option' ? null : value)}
                            value={selectedMejaId || 'no_table_option'}
                        >
                            <SelectTrigger
                                id="meja-select"
                                className="h-10 w-full rounded-lg border-gray-300 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <SelectValue placeholder="Pilih Meja (Opsional)" />
                            </SelectTrigger>
                            <SelectContent className="max-h-60 overflow-y-auto rounded-lg">
                                <SelectItem value="no_table_option" className="px-3 py-2 text-base">
                                    Tanpa Meja
                                </SelectItem>
                                {mejas.map((meja) => (
                                    <SelectItem key={meja.id} value={String(meja.id)} className="px-3 py-2 text-base">
                                        {meja.nama} ({meja.status})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}

                {/* Cart Table */}
                <CartTable cartItems={cartItems} updateQuantity={updateQuantity} removeItemFromCart={removeItemFromCart} />

                <div className="space-y-3 rounded-xl bg-gray-50 p-6 text-right shadow-inner">
                    <div className="flex justify-between text-base text-gray-700">
                        <span>Sub Total:</span>
                        <span className="font-medium">{new Intl.NumberFormat('id-ID').format(subTotal)}</span>
                    </div>
                    <div className="flex justify-between text-base text-gray-700">
                        <span>PPN ({settings.ppn || 0}%):</span>
                        <span className="font-medium">{new Intl.NumberFormat('id-ID').format(ppnAmount)}</span>
                    </div>
                    <div className="flex justify-between text-base text-gray-700">
                        <span>Biaya Layanan:</span>
                        <span className="font-medium">{new Intl.NumberFormat('id-ID').format(biayaLayanan)}</span>
                    </div>
                    <div className="mt-4 flex justify-between border-t border-gray-300 pt-4 text-2xl font-extrabold text-emerald-600">
                        <span>TOTAL:</span>
                        <span>{new Intl.NumberFormat('id-ID').format(totalAmount)}</span>
                    </div>
                </div>

                {/* Payment Method Section */}
                <PaymentSection
                    paymentMethod={paymentMethod}
                    setPaymentMethod={setPaymentMethod}
                    amountPaid={amountPaid}
                    setAmountPaid={setAmountPaid}
                    totalAmount={totalAmount}
                    change={change}
                />

                <div className="mt-6 space-y-3 pb-4">
                    <Button
                        className="h-14 w-full rounded-xl bg-biru text-lg font-semibold shadow-md transition-colors duration-200 hover:bg-indigo-700"
                        onClick={handleProcessPayment}
                        disabled={cartItems.length === 0 || (paymentMethod === 'cash' && amountPaid < totalAmount) || isLoading}
                    >
                        {isLoading ? <Loader2 className="mr-3 h-5 w-5 animate-spin" /> : null}
                        Proses Pembayaran
                    </Button>
                    <Button
                        className="h-12 w-full rounded-xl border-red-500 text-base font-semibold text-red-500 transition-colors duration-200 hover:bg-red-50 hover:text-red-600"
                        variant="outline"
                        onClick={handleCancelTransaction}
                        disabled={isLoading}
                    >
                        Batalkan Transaksi
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
};

export default OrderSummary;
