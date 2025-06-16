// src/pages/CashierPage.tsx

import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import React, { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

// Import Layout dan Komponen yang sudah dipecah
import CashierLayout from '@/layouts/CashierLayout';
import CashierNavbar from './components/CashierNavbar';
import MobileMenu from './components/MobileMenu';
import OrderSummary from './components/OrderSummary';
import ProductGrid from './components/ProductGrid';
import TransactionHistory from './components/TransactionHistory';

// Import Dialog components for the success pop-up
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowLeft, CheckCircle, Printer } from 'lucide-react';

// Import Types (pastikan path ini sudah benar di project Anda)
import { CartItem } from '@/types';
import { Kategori } from '@/types/kategori';
import { Meja } from '@/types/meja';
import { Penjualan } from '@/types/penjualan';
import { Product } from '@/types/product';
import { Setting } from '@/types/setting';
import { User } from '@/types/user';

// Interface untuk data invoice yang akan diterima di pop-up
interface DetailPenjualanWithProduk {
    id: number;
    penjualan_id: number;
    produk_id: number;
    qty: number;
    harga_saat_jual: number;
    subtotal_item: number;
    produk: Product;
}

interface InvoiceForPopUp extends Omit<Penjualan, 'details'> {
    details: DetailPenjualanWithProduk[];
}

// Main Page Props (dari Inertia.js)
// Ini adalah tipe untuk *seluruh* objek `props` yang diterima dari Laravel
interface CashierPageProps {
    products: Product[];
    mejas: Meja[];
    customers: User[];
    settings: Setting;
    kategoris: Kategori[];
    latestTransactions: Penjualan[];
    auth: {
        user: User;
    };
    filters?: {
        invoice_number?: string;
    };
    // Tambahkan index signature agar TypeScript tidak error saat mengakses props yang tidak didefinisikan secara eksplisit
    [key: string]: any;
}

const CashierPage: React.FC<CashierPageProps> = ({
    // Destructure props yang hanya untuk inisialisasi awal.
    // Ini adalah data yang diterima saat komponen pertama kali dirender dari server.
    products: initialProducts,
    mejas: initialMejas,
    customers: initialCustomers,
    settings: initialSettings,
    kategoris: initialKategoris,
    latestTransactions: initialLatestTransactions,
    auth: initialAuth,
}) => {
    // Access page props using usePage hook.
    // Ini akan menjadi sumber kebenaran untuk props halaman yang diperbarui oleh Inertia.
    const { props: currentInertiaPageProps } = usePage<CashierPageProps>();

    // States that hold the data.
    // Initialize them with the initial props from the server render.
    // They will be updated later by `useEffect` hook whenever `currentInertiaPageProps` change.
    const [products, setProducts] = useState(initialProducts);
    const [mejas, setMejas] = useState(initialMejas);
    const [customers, setCustomers] = useState(initialCustomers);
    const [settings, setSettings] = useState(initialSettings);
    const [kategoris, setKategoris] = useState(initialKategoris);
    const [transactionHistory, setTransactionHistory] = useState<Penjualan[]>(initialLatestTransactions);

    // --- State Lokal untuk fungsi kasir ---
    const [cartItems, setCartItems] = useState<CartItem[]>([]);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
    const [selectedCustomerId, setSelectedCustomerId] = useState<string | null>(null);
    const [selectedMejaId, setSelectedMejaId] = useState<string | null>(null);
    const [paymentMethod, setPaymentMethod] = useState<'cash' | 'duitku'>('cash');
    const [amountPaid, setAmountPaid] = useState<number>(0);
    const [transactionType, setTransactionType] = useState<'dine_in' | 'take_away'>('dine_in');
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const [showHistory, setShowHistory] = useState(false);
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    // --- State untuk Success Pop-up ---
    const [isSuccessModalOpen, setIsSuccessModalOpen] = useState(false);
    const [successInvoice, setSuccessInvoice] = useState<InvoiceForPopUp | null>(null);
    const [successChange, setSuccessChange] = useState<number | undefined>(undefined);

    // --- EFFECT: Update state when `currentInertiaPageProps` (from usePage) change ---
    // This effect ensures that if the page props are updated (e.g., due to a full Inertia visit,
    // or router.reload() outside of a specific onSuccess callback), our local states reflect it.
    useEffect(() => {
        // Lakukan pengecekan tipe untuk menghindari potensi 'undefined' jika prop tidak selalu ada
        // Meskipun CashierPageProps sudah didefinisikan, `usePage().props` bisa lebih umum.
        // Konversi ini memberi tahu TypeScript bahwa kita mengharapkan properti-properti ini ada.
        setProducts(currentInertiaPageProps.products);
        setMejas(currentInertiaPageProps.mejas);
        setCustomers(currentInertiaPageProps.customers);
        setSettings(currentInertiaPageProps.settings);
        setKategoris(currentInertiaPageProps.kategoris);
        setTransactionHistory(currentInertiaPageProps.latestTransactions);
    }, [currentInertiaPageProps]); // Depend on the entire props object from usePage

    // --- EFFECT: Menangani redirect Duitku dan pembaruan data setelahnya ---
    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const duitkuStatus = urlParams.get('status');
        const duitkuOrderId = urlParams.get('merchantOrderId');

        if (duitkuStatus) {
            urlParams.delete('status');
            urlParams.delete('reference');
            urlParams.delete('merchantOrderId');
            window.history.replaceState({}, document.title, `${window.location.pathname}${urlParams.toString() ? '?' + urlParams.toString() : ''}`);

            if (duitkuStatus === 'SUCCESS') {
                toast.success('Pembayaran Duitku Berhasil!', {
                    description: `Transaksi ${duitkuOrderId} telah selesai.`,
                    duration: 5000,
                });
            } else if (duitkuStatus === 'FAILED' || duitkuStatus === 'CANCELLED') {
                toast.error('Pembayaran Duitku Gagal', {
                    description: `Transaksi ${duitkuOrderId} dibatalkan atau gagal.`,
                    duration: 5000,
                });
            } else {
                toast.info('Status Pembayaran Duitku', {
                    description: `Status: ${duitkuStatus} untuk Order ID: ${duitkuOrderId}.`,
                    duration: 5000,
                });
            }
            // Reset form dan reload halaman untuk mendapatkan state terbaru termasuk history
            setCartItems([]);
            setAmountPaid(0);
            setSelectedCustomerId(null);
            setSelectedMejaId(null);
            setPaymentMethod('cash');
            setTransactionType('dine_in');
            router.reload({
                only: ['products', 'mejas', 'customers', 'settings', 'kategoris', 'latestTransactions'],
                onSuccess: (page) => {
                    // --- FIX TYPE ASSERTION: Convert to unknown first, then to CashierPageProps ---
                    const typedPageProps = page.props as unknown as CashierPageProps;
                    setProducts(typedPageProps.products);
                    setMejas(typedPageProps.mejas);
                    setCustomers(typedPageProps.customers);
                    setSettings(typedPageProps.settings);
                    setKategoris(typedPageProps.kategoris);
                    setTransactionHistory(typedPageProps.latestTransactions);
                },
            });
        }
    }, []); // Dependensi diatur kosong, karena ini hanya perlu dijalankan sekali saat komponen mount.

    // --- Filter Produk Berdasarkan Pencarian dan Kategori (memakai state `products`) ---
    const filteredProducts = useMemo(() => {
        return products.filter((product) => {
            const matchesSearch = product.nama_produk.toLowerCase().includes(searchTerm.toLowerCase());
            const matchesCategory = selectedCategoryId === null || product.kategori_id === selectedCategoryId;
            return matchesSearch && matchesCategory;
        });
    }, [products, searchTerm, selectedCategoryId]);

    // --- Perhitungan Ringkasan Pesanan (memakai state `settings`) ---
    const subTotal = useMemo(() => {
        return cartItems.reduce((acc, item) => acc + (item.harga_jual || 0) * item.qty, 0);
    }, [cartItems]);

    const ppnAmount = useMemo(() => {
        return (subTotal * (settings.ppn || 0)) / 100;
    }, [subTotal, settings.ppn]);

    const biayaLayanan = useMemo(() => {
        // KOREKSI: Menggunakan `settings.biaya_lainnya` sesuai dengan kode backend
        return transactionType === 'dine_in' ? settings.biaya_lainnya || 0 : 0;
    }, [transactionType, settings.biaya_lainnya]);

    const totalAmount = useMemo(() => {
        return (subTotal || 0) + (ppnAmount || 0) + (biayaLayanan || 0);
    }, [subTotal, ppnAmount, biayaLayanan]);

    const change = useMemo(() => {
        return paymentMethod === 'cash' ? (amountPaid || 0) - (totalAmount || 0) : 0;
    }, [paymentMethod, amountPaid, totalAmount]);

    // --- Fungsi Penambahan dan Pengelolaan Keranjang ---
    const addProductToCart = (product: Product) => {
        if (product.stok === 0) {
            toast.error('Stok Habis', {
                description: `Produk ${product.nama_produk} saat ini tidak tersedia.`,
            });
            return;
        }

        const existingItem = cartItems.find((item) => item.id === product.id);
        if (existingItem) {
            if (existingItem.qty < product.stok) {
                setCartItems((prev) => prev.map((item) => (item.id === product.id ? { ...item, qty: item.qty + 1 } : item)));
            } else {
                toast.error('Stok Tidak Cukup', {
                    description: `Stok ${product.nama_produk} saat ini hanya ${product.stok}.`,
                });
            }
        } else {
            setCartItems((prev) => [...prev, { ...product, qty: 1 }]);
        }
    };

    const updateQuantity = (productId: number, delta: number) => {
        setCartItems((prev) => {
            const updatedItems = prev
                .map((item) => {
                    if (item.id === productId) {
                        const newQty = item.qty + delta;
                        const productInStock = products.find((p) => p.id === productId);

                        if (newQty > 0 && productInStock && newQty <= productInStock.stok) {
                            return { ...item, qty: newQty };
                        } else if (newQty > productInStock!.stok) {
                            toast.error('Stok Tidak Cukup', {
                                description: `Maksimal stok ${productInStock?.nama_produk} adalah ${productInStock?.stok}.`,
                            });
                            return item;
                        }
                    }
                    return item;
                })
                .filter((item) => item.qty > 0);
            return updatedItems;
        });
    };

    const removeItemFromCart = (productId: number) => {
        setCartItems((prev) => prev.filter((item) => item.id !== productId));
        toast.info('Item Dihapus', {
            description: 'Produk berhasil dihapus dari keranjang.',
        });
    };

    // Fungsi untuk mendapatkan jumlah produk di keranjang
    const getProductQuantityInCart = (productId: number) => {
        const item = cartItems.find((item) => item.id === productId);
        return item ? item.qty : 0;
    };

    // --- Fungsi Proses Pembayaran ---
    const handleProcessPayment = async () => {
        if (cartItems.length === 0) {
            toast.error('Keranjang Kosong', {
                description: 'Silakan tambahkan produk ke keranjang sebelum melanjutkan.',
            });
            return;
        }

        if (paymentMethod === 'cash' && amountPaid < totalAmount) {
            toast.error('Pembayaran Kurang', {
                description: `Jumlah uang tunai kurang. Dibutuhkan: ${new Intl.NumberFormat('id-ID').format(totalAmount)}.`,
            });
            return;
        }

        setIsLoading(true);

        try {
            const payload = {
                cartItems: cartItems.map((item) => ({ id: item.id, qty: item.qty })),
                paymentMethod,
                amountPaid: paymentMethod === 'cash' ? amountPaid : null,
                customerId: selectedCustomerId,
                mejaId: transactionType === 'dine_in' ? selectedMejaId : null,
                type: transactionType,
            };

            const response = await axios.post(route('pos.process_sale'), payload);
            const data = response.data; // Data dari respons JSON Laravel

            if (data.success) {
                // Reset form di frontend terlepas dari metode pembayaran
                setCartItems([]);
                setAmountPaid(0);
                setSelectedCustomerId(null);
                setSelectedMejaId(null);
                setPaymentMethod('cash');
                setTransactionType('dine_in');

                if (paymentMethod === 'cash') {
                    // Tampilkan pop-up sukses dan update history
                    setSuccessInvoice(data.invoice as InvoiceForPopUp); // Type assertion untuk data.invoice
                    setSuccessChange(data.change);
                    setIsSuccessModalOpen(true);

                    toast.success('Pembayaran Tunai Berhasil!', {
                        description: `Transaksi selesai. Kembalian: ${new Intl.NumberFormat('id-ID').format(data.change)}.`,
                        duration: 3000,
                    });
                    // Reload data untuk update riwayat transaksi, stok produk, dll.
                    router.reload({
                        // Specify only the props that need to be reloaded for efficiency
                        only: ['products', 'mejas', 'latestTransactions'],
                        onSuccess: (page) => {
                            // Type assertion here for the router.reload() success callback
                            const typedPageProps = page.props as unknown as CashierPageProps; // THE FIX
                            setProducts(typedPageProps.products);
                            setMejas(typedPageProps.mejas);
                            setTransactionHistory(typedPageProps.latestTransactions);
                            // customers, settings, kategoris tidak di-reload dengan `only` di atas,
                            // jadi tidak perlu di-update di sini untuk menghindari error undefined.
                        },
                    });
                } else if (paymentMethod === 'duitku') {
                    toast.info('Mengarahkan ke Duitku...', {
                        description: 'Harap tunggu, Anda akan diarahkan ke halaman pembayaran.',
                        duration: 3000,
                    });
                    if (data.paymentUrl) {
                        window.location.href = data.paymentUrl;
                    }
                }
            } else {
                toast.error('Gagal Memproses Pembayaran', {
                    description: data.message || 'Terjadi kesalahan yang tidak diketahui.',
                });
            }
        } catch (error: any) {
            const errorMessage = error.response?.data?.message || 'Terjadi kesalahan saat memproses pembayaran.';
            const validationErrors = error.response?.data?.errors;

            if (validationErrors) {
                const flatErrors = Object.values(validationErrors).flat().join(', ');
                toast.error('Validasi Gagal', {
                    description: flatErrors,
                });
                console.error('Axios Validation Errors:', validationErrors);
            } else {
                toast.error('Gagal Memproses Pembayaran', {
                    description: errorMessage,
                });
                console.error('Axios Error:', error);
            }
        } finally {
            setIsLoading(false);
        }
    };

    const handleCancelTransaction = () => {
        setCartItems([]);
        setAmountPaid(0);
        setSelectedCustomerId(null);
        setSelectedMejaId(null);
        setPaymentMethod('cash');
        setTransactionType('dine_in');
        toast.info('Transaksi Dibatalkan', {
            description: 'Keranjang belanja telah dikosongkan dan reset ke kondisi awal.',
        });
    };

    const handleLogout = () => {
        router.post(route('logout'));
    };

    // Fungsi untuk mencetak invoice dari modal sukses
    const handlePrintFromModal = () => {
        if (successInvoice) {
            const printUrl = route('penjualan.print', successInvoice.id);
            window.open(printUrl, '_blank');
        }
    };

    return (
        <CashierLayout>
            <Head title="Kasir POS" />

            {/* Navbar */}
            <CashierNavbar
                settings={settings}
                showHistory={showHistory}
                setShowHistory={setShowHistory}
                isMobileMenuOpen={isMobileMenuOpen}
                setIsMobileMenuOpen={setIsMobileMenuOpen}
                auth={initialAuth} // Menggunakan initialAuth karena stabil
                onLogout={handleLogout}
            />

            {/* Mobile Menu */}
            {isMobileMenuOpen && (
                <MobileMenu
                    showHistory={showHistory}
                    setShowHistory={setShowHistory}
                    setIsMobileMenuOpen={setIsMobileMenuOpen}
                    auth={initialAuth} // Menggunakan initialAuth
                    onLogout={handleLogout}
                />
            )}

            {/* Main Content Area */}
            <div className="flex flex-1 overflow-auto bg-[#F5F5F5] p-4 lg:p-6">
                {!showHistory ? (
                    <div className="grid w-full grid-cols-1 gap-6 lg:grid-cols-3">
                        {/* Bagian Daftar Produk */}
                        <ProductGrid
                            products={products}
                            kategoris={kategoris}
                            searchTerm={searchTerm}
                            setSearchTerm={setSearchTerm}
                            selectedCategoryId={selectedCategoryId}
                            setSelectedCategoryId={setSelectedCategoryId}
                            filteredProducts={filteredProducts}
                            addProductToCart={addProductToCart}
                            updateQuantity={updateQuantity}
                            removeItemFromCart={removeItemFromCart}
                            getProductQuantityInCart={getProductQuantityInCart}
                        />

                        {/* Bagian Ringkasan Keranjang & Pembayaran */}
                        <OrderSummary
                            customers={customers}
                            mejas={mejas}
                            settings={settings}
                            cartItems={cartItems}
                            selectedCustomerId={selectedCustomerId}
                            setSelectedCustomerId={setSelectedCustomerId}
                            transactionType={transactionType}
                            setTransactionType={setTransactionType}
                            selectedMejaId={selectedMejaId}
                            setSelectedMejaId={setSelectedMejaId}
                            paymentMethod={paymentMethod}
                            setPaymentMethod={setPaymentMethod}
                            amountPaid={amountPaid}
                            setAmountPaid={setAmountPaid}
                            subTotal={subTotal}
                            ppnAmount={ppnAmount}
                            biayaLayanan={biayaLayanan}
                            totalAmount={totalAmount}
                            change={change}
                            isLoading={isLoading}
                            updateQuantity={updateQuantity}
                            removeItemFromCart={removeItemFromCart}
                            handleProcessPayment={handleProcessPayment}
                            handleCancelTransaction={handleCancelTransaction}
                        />
                    </div>
                ) : (
                    <TransactionHistory
                        transactionHistory={transactionHistory}
                        isLoading={isLoading}
                        initialInvoiceSearchTerm={currentInertiaPageProps.filters?.invoice_number || ''} // Menggunakan currentInertiaPageProps
                    />
                )}
            </div>
            {/* Custom scrollbar styles */}
            <style>{`
                .custom-scrollbar::-webkit-scrollbar {
                    width: 8px;
                    height: 8px;
                }
                .custom-scrollbar::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 10px;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb {
                    background: #cbd5e1;
                    border-radius: 10px;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                    background: #94a3b8;
                }
            `}</style>

            {/* --- Success Pop-up Dialog --- */}
            <Dialog open={isSuccessModalOpen} onOpenChange={setIsSuccessModalOpen}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader className="flex flex-col items-center gap-2">
                        <CheckCircle className="h-16 w-16 text-emerald-500" />
                        <DialogTitle className="text-center text-2xl font-bold text-gray-800">Transaksi Berhasil!</DialogTitle>
                        <DialogDescription className="text-center text-gray-600">Rincian pembelian Anda:</DialogDescription>
                    </DialogHeader>
                    {successInvoice && (
                        <div className="grid gap-4 py-4 text-sm">
                            <div className="grid grid-cols-3 items-center gap-4">
                                <p className="text-gray-500">Invoice:</p>
                                <p className="col-span-2 font-medium">{successInvoice.invoice_number}</p>
                            </div>
                            <div className="grid grid-cols-3 items-center gap-4">
                                <p className="text-gray-500">Tanggal:</p>
                                <p className="col-span-2 font-medium">{new Date(successInvoice.created_at).toLocaleString('id-ID')}</p>
                            </div>
                            <div className="grid grid-cols-3 items-center gap-4">
                                <p className="text-gray-500">Pelanggan:</p>
                                <p className="col-span-2 font-medium">{successInvoice.customer?.name || 'Guest'}</p>
                            </div>
                            {successInvoice.type === 'dine_in' && successInvoice.meja && (
                                <div className="grid grid-cols-3 items-center gap-4">
                                    <p className="text-gray-500">Meja:</p>
                                    <p className="col-span-2 font-medium">{successInvoice.meja.nama}</p>
                                </div>
                            )}
                            <div className="grid grid-cols-3 items-center gap-4">
                                <p className="text-gray-500">Metode Bayar:</p>
                                <p className="col-span-2 font-medium capitalize">{successInvoice.payment_method}</p>
                            </div>
                            <div className="mt-2 grid grid-cols-3 items-center gap-4 border-t pt-2 text-lg font-bold">
                                <p className="text-gray-800">Total:</p>
                                <p className="col-span-2 text-emerald-600">{new Intl.NumberFormat('id-ID').format(successInvoice.total)}</p>
                            </div>
                            {successChange !== undefined && successInvoice.payment_method === 'cash' && (
                                <div className="grid grid-cols-3 items-center gap-4 text-lg font-bold">
                                    <p className="text-gray-800">Kembalian:</p>
                                    <p className="col-span-2 text-indigo-600">{new Intl.NumberFormat('id-ID').format(successChange)}</p>
                                </div>
                            )}

                            <h4 className="mt-4 mb-2 border-b pb-2 font-semibold">Item Pembelian:</h4>
                            {successInvoice.details && successInvoice.details.length > 0 ? (
                                <div className="max-h-40 overflow-y-auto rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Produk</TableHead>
                                                <TableHead className="text-center">Qty</TableHead>
                                                <TableHead className="text-right">Subtotal</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {successInvoice.details.map((detail) => (
                                                <TableRow key={detail.id}>
                                                    <TableCell>{detail.produk?.nama_produk || 'Produk Tidak Ditemukan'}</TableCell>
                                                    <TableCell className="text-center">{detail.qty}</TableCell>
                                                    <TableCell className="text-right">
                                                        {new Intl.NumberFormat('id-ID').format(detail.qty * detail.produk.harga_jual)}
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
                    )}
                    <DialogFooter className="mt-6 flex flex-col gap-4 sm:flex-row">
                        <Button
                            onClick={handlePrintFromModal}
                            className="flex-1 rounded-lg bg-green-600 py-3 text-base text-white hover:bg-green-700"
                        >
                            <Printer className="mr-2 h-5 w-5" /> Cetak Struk
                        </Button>
                        <Button
                            onClick={() => setIsSuccessModalOpen(false)} // Menutup modal
                            variant="outline"
                            className="flex-1 rounded-lg border-biru py-3 text-base text-biru hover:bg-biru/10"
                        >
                            <ArrowLeft className="mr-2 h-5 w-5" /> Kembali ke Kasir
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </CashierLayout>
    );
};

export default CashierPage;
