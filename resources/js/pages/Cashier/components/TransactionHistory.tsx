import { Card, CardContent } from '@/components/ui/card';
import { Penjualan } from '@/types/penjualan';
import { Loader2 } from 'lucide-react';
import React from 'react';
import { columns } from './columns';
import { DataTable } from './data-table';

interface TransactionHistoryProps {
    transactionHistory: Penjualan[];
    isLoading: boolean;
    // --- FIX: Add the missing prop here ---
    initialInvoiceSearchTerm: string;
}

const TransactionHistory: React.FC<TransactionHistoryProps> = ({ transactionHistory, isLoading, initialInvoiceSearchTerm }) => {
    const handlePrintInvoice = (transaction: Penjualan) => {
        const printUrl = route('penjualan.print', transaction.id);
        window.open(printUrl, '_blank');
    };

    return (
        <Card className="flex h-full w-full flex-col rounded-2xl bg-white p-6 shadow-md">
            <CardContent className="flex-1 px-0 pt-4 pb-0">
                {isLoading ? (
                    <div className="flex h-full items-center justify-center">
                        <Loader2 className="h-10 w-10 animate-spin text-indigo-500" />
                        <p className="ml-3 text-lg text-gray-600">Memuat riwayat...</p>
                    </div>
                ) : transactionHistory.length === 0 ? (
                    <p className="py-10 text-center text-gray-500">Tidak ada riwayat transaksi.</p>
                ) : (
                    // You might need to pass initialInvoiceSearchTerm to DataTable if it uses it internally
                    <DataTable columns={columns(handlePrintInvoice)} data={transactionHistory} initialInvoiceSearchTerm={initialInvoiceSearchTerm} />
                )}
            </CardContent>
        </Card>
    );
};

export default TransactionHistory;
