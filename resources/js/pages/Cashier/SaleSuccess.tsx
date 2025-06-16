// src/pages/SaleSuccess.tsx

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import CashierLayout from '@/layouts/CashierLayout';
import { Penjualan } from '@/types/penjualan'; // Pastikan path benar
import { Product } from '@/types/product';
import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle, Printer } from 'lucide-react';
import React from 'react';

// Perluasan interface DetailPenjualan untuk menyertakan Produk
interface DetailPenjualanWithProduk {
    id: number;
    penjualan_id: number;
    produk_id: number;
    qty: number;
    harga_saat_jual: number;
    subtotal_item: number;
    produk: Product; // Memastikan detail produk disertakan
}

// Perbarui interface Penjualan untuk halaman ini
interface PenjualanForSuccessPage extends Omit<Penjualan, 'details'> {
    details: DetailPenjualanWithProduk[]; // Gunakan detail yang sudah menyertakan produk
}

// DEFINISIKAN INTERFACE UNTUK PROPS DARI usePage
interface SaleSuccessPageProps {
    invoice: PenjualanForSuccessPage;
    change?: number; // Kembalian hanya untuk pembayaran tunai
    // --- TAMBAHKAN INDEX SIGNATURE INI ---
    [key: string]: any; // Ini memenuhi persyaratan Inertia PageProps
}

const SaleSuccess: React.FC = () => {
    const { invoice, change } = usePage<SaleSuccessPageProps>().props;

    // Pastikan invoice tidak null atau undefined sebelum mencoba merender
    if (!invoice) {
        router.visit(route('cashier'), {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                alert('Data transaksi tidak ditemukan.');
            },
        });
        return null;
    }

    const handleBackToCashier = () => {
        router.visit(route('cashier'));
    };

    const handlePrintInvoice = () => {
        const printUrl = route('penjualan.print', invoice.id);
        window.open(printUrl, '_blank');
    };

    return (
        <CashierLayout>
            <Head title="Transaksi Berhasil!" />
            <div className="flex min-h-screen items-center justify-center bg-gray-50 p-4">
                <Card className="w-full max-w-2xl rounded-lg shadow-xl">
                    <CardHeader className="flex flex-col items-center gap-4 py-8">
                        <CheckCircle className="h-20 w-20 text-emerald-500" />
                        <CardTitle className="text-center text-3xl font-bold text-gray-800">Transaksi Berhasil!</CardTitle>
                        <p className="text-lg text-gray-600">
                            Invoice: <span className="font-semibold">#{invoice.invoice_number}</span>
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-6 pb-6">
                        <div className="grid gap-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-gray-600">Tanggal:</span>
                                <span className="font-medium">{new Date(invoice.created_at).toLocaleString('id-ID')}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-600">Pelanggan:</span>
                                <span className="font-medium">{invoice.customer?.name || 'Guest'}</span>
                            </div>
                            {invoice.type === 'dine_in' && invoice.meja && (
                                <div className="flex justify-between">
                                    <span className="text-gray-600">Meja:</span>
                                    <span className="font-medium">{invoice.meja.nama}</span>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <span className="text-gray-600">Metode Pembayaran:</span>
                                <span className="font-medium capitalize">{invoice.payment_method}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-600">Tipe Transaksi:</span>
                                <span className="font-medium capitalize">{invoice.type?.replace('_', ' ') || 'N/A'}</span>
                            </div>
                            <div className="mt-4 flex justify-between border-t pt-4 text-lg font-bold">
                                <span className="text-gray-800">Total Pembayaran:</span>
                                <span className="text-emerald-600">{new Intl.NumberFormat('id-ID').format(invoice.total)}</span>
                            </div>
                            {change !== undefined && invoice.payment_method === 'cash' && (
                                <div className="flex justify-between text-lg font-bold">
                                    <span className="text-gray-800">Kembalian:</span>
                                    <span className="text-indigo-600">{new Intl.NumberFormat('id-ID').format(change)}</span>
                                </div>
                            )}
                        </div>

                        <div className="space-y-2">
                            <h4 className="border-b pb-2 text-lg font-semibold">Item Pembelian:</h4>
                            {invoice.details && invoice.details.length > 0 ? (
                                <div className="max-h-60 overflow-y-auto rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Produk</TableHead>
                                                <TableHead className="text-center">Qty</TableHead>
                                                <TableHead className="text-right">Harga</TableHead>
                                                <TableHead className="text-right">Subtotal</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {invoice.details.map((detail) => (
                                                <TableRow key={detail.id}>
                                                    <TableCell>{detail.produk?.nama_produk || 'Produk Tidak Ditemukan'}</TableCell>
                                                    <TableCell className="text-center">{detail.qty}</TableCell>
                                                    <TableCell className="text-right">
                                                        {new Intl.NumberFormat('id-ID').format(detail.harga_saat_jual)}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {new Intl.NumberFormat('id-ID').format(detail.subtotal_item)}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            ) : (
                                <p className="text-gray-500 italic">Tidak ada item dalam transaksi ini.</p>
                            )}
                        </div>

                        <div className="mt-6 flex flex-col gap-4 sm:flex-row">
                            <Button
                                onClick={handlePrintInvoice}
                                className="flex-1 rounded-lg bg-green-600 py-3 text-base text-white hover:bg-green-700"
                            >
                                <Printer className="mr-2 h-5 w-5" /> Cetak Struk
                            </Button>
                            <Button
                                onClick={handleBackToCashier}
                                variant="outline"
                                className="flex-1 rounded-lg border-biru py-3 text-base text-biru hover:bg-biru/10"
                            >
                                <ArrowLeft className="mr-2 h-5 w-5" /> Kembali ke Kasir
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </CashierLayout>
    );
};

export default SaleSuccess;
