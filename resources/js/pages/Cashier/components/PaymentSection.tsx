// src/Components/OrderSummary/PaymentSection.tsx

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Wallet } from 'lucide-react';
import React from 'react';

interface PaymentSectionProps {
    paymentMethod: 'cash' | 'duitku';
    setPaymentMethod: (method: 'cash' | 'duitku') => void;
    amountPaid: number;
    setAmountPaid: (amount: number) => void;
    totalAmount: number;
    change: number;
}

const PaymentSection: React.FC<PaymentSectionProps> = ({ paymentMethod, setPaymentMethod, amountPaid, setAmountPaid, change }) => {
    return (
        <div>
            <h3 className="mb-3 flex items-center text-lg font-semibold text-gray-800">
                <Wallet className="mr-2 h-5 w-5 text-gray-500" /> Metode Pembayaran
            </h3>
            <div className="grid grid-cols-2 gap-2">
                <Button
                    variant={paymentMethod === 'cash' ? 'default' : 'outline'}
                    onClick={() => setPaymentMethod('cash')}
                    className={`h-10 flex-1 rounded-lg text-base transition-colors duration-200 ${paymentMethod === 'cash' ? 'bg-biru text-white shadow-sm hover:bg-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}
                >
                    Tunai
                </Button>
                <Button
                    variant={paymentMethod === 'duitku' ? 'default' : 'outline'}
                    onClick={() => setPaymentMethod('duitku')}
                    className={`h-10 flex-1 rounded-lg text-base transition-colors duration-200 ${paymentMethod === 'duitku' ? 'bg-biru text-white shadow-sm hover:bg-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}
                >
                    Duitku
                </Button>
            </div>

            {paymentMethod === 'cash' && (
                <div className="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 shadow-sm">
                    <Label htmlFor="amount-paid" className="mb-2 block text-sm font-medium text-gray-700">
                        Jumlah Pembayaran Tunai
                    </Label>
                    <Input
                        id="amount-paid"
                        type="number"
                        placeholder="0"
                        value={amountPaid === 0 ? '' : amountPaid}
                        onChange={(e) => setAmountPaid(parseFloat(e.target.value) || 0)}
                        className="h-10 w-full rounded-lg px-3 text-base focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p className="mt-3 text-sm font-medium text-gray-600">
                        Kembalian: <span className="font-bold text-biru">{new Intl.NumberFormat('id-ID').format(change)}</span>
                    </p>
                </div>
            )}
        </div>
    );
};

export default PaymentSection;
