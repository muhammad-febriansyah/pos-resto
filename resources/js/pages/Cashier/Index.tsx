import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import React, { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import CashierLayout from '@/layouts/CashierLayout';
import CashierNavbar from './components/CashierNavbar';
import MobileMenu from './components/MobileMenu';
import OrderSummary from './components/OrderSummary';
import ProductGrid from './components/ProductGrid';
import TransactionHistory from './components/TransactionHistory';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowLeft, CheckCircle, Printer } from 'lucide-react';

import { CartItem } from '@/types';
import { Kategori } from '@/types/kategori';
import { Meja } from '@/types/meja';
import { Penjualan } from '@/types/penjualan';
import { Product } from '@/types/product';
import { Setting } from '@/types/setting';
import { User } from '@/types/user';

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
    [key: string]: any;
}

declare global {
    interface Window {
        snap: {
            pay: (
                token: string,
                options?: {
                    onSuccess?: (result: any) => void;
                    onPending?: (result: any) => void;
                    onError?: (result: any) => void;
                    onClose?: () => void;
                },
            ) => void;
        };
    }
}

const CashierPage: React.FC<CashierPageProps> = ({
    products: initialProducts,
    mejas: initialMejas,
    customers: initialCustomers,
    settings: initialSettings,
    kategoris: initialKategoris,
    latestTransactions: initialLatestTransactions,
    auth: initialAuth,
}) => {
    const { props: currentInertiaPageProps } = usePage<CashierPageProps>();

    const [products, setProducts] = useState(initialProducts);
    const [mejas, setMejas] = useState(initialMejas);
    const [customers, setCustomers] = useState(initialCustomers);
    const [settings, setSettings] = useState(initialSettings);
    const [kategoris, setKategoris] = useState(initialKategoris);
    const [transactionHistory, setTransactionHistory] = useState<Penjualan[]>(initialLatestTransactions);

    const [cartItems, setCartItems] = useState<CartItem[]>([]);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
    const [selectedCustomerId, setSelectedCustomerId] = useState<string | null>(null);
    const [selectedMejaId, setSelectedMejaId] = useState<string | null>(null);
    const [paymentMethod, setPaymentMethod] = useState<'cash' | 'midtrans'>('cash');
    const [amountPaid, setAmountPaid] = useState<number>(0);
    const [transactionType, setTransactionType] = useState<'dine_in' | 'take_away'>('dine_in');
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const [showHistory, setShowHistory] = useState(false);
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    const [isSuccessModalOpen, setIsSuccessModalOpen] = useState(false);
    const [successInvoice, setSuccessInvoice] = useState<InvoiceForPopUp | null>(null);
    const [successChange, setSuccessChange] = useState<number | undefined>(undefined);

    // State to track if Snap.js is loaded
    const [isSnapLoaded, setIsSnapLoaded] = useState(false);

    useEffect(() => {
        setProducts(currentInertiaPageProps.products);
        setMejas(currentInertiaPageProps.mejas);
        setCustomers(currentInertiaPageProps.customers);
        setSettings(currentInertiaPageProps.settings);
        setKategoris(currentInertiaPageProps.kategoris);
        setTransactionHistory(currentInertiaPageProps.latestTransactions);
    }, [currentInertiaPageProps]);

    // --- Dynamic Snap.js Loading ---
    useEffect(() => {
        const loadSnapScript = () => {
            if (document.getElementById('midtrans-snap-script')) {
                setIsSnapLoaded(true);
                return;
            }

            const script = document.createElement('script');
            // Use the sandbox URL for development/testing
            // For production, change to 'https://app.midtrans.com/snap/snap.js'
            script.src = 'https://app.sandbox.midtrans.com/snap/snap.js'; // Make sure this matches your backend's isProduction setting
            script.id = 'midtrans-snap-script';
            // IMPORTANT: Replace with your actual Midtrans Client Key from your .env or similar
            // Example for Vite/Next.js: import.meta.env.VITE_MIDTRANS_CLIENT_KEY or process.env.NEXT_PUBLIC_MIDTRANS_CLIENT_KEY
            // Ensure this environment variable exists and is correctly configured for your frontend.
            script.setAttribute('data-client-key', import.meta.env.VITE_MIDTRANS_CLIENT_KEY || '');

            script.onload = () => {
                console.log('Midtrans Snap.js loaded successfully!');
                setIsSnapLoaded(true);
            };

            script.onerror = (error) => {
                console.error('Failed to load Midtrans Snap.js:', error);
                toast.error('Gagal memuat Midtrans Snap.js', {
                    description: 'Silakan coba refresh halaman atau hubungi administrator.',
                });
            };

            document.body.appendChild(script);
        };

        loadSnapScript();

        // Optional cleanup: remove script when component unmounts if not needed globally
        return () => {
            const script = document.getElementById('midtrans-snap-script');
            if (script && document.body.contains(script)) {
                // document.body.removeChild(script); // Uncomment if you want to remove the script on unmount
            }
        };
    }, []); // Empty dependency array ensures this runs once on mount

    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const midtransStatus = urlParams.get('status');
        const midtransOrderId = urlParams.get('order_id');

        if (midtransStatus) {
            // Clean up URL parameters after processing
            urlParams.delete('status');
            urlParams.delete('order_id');
            urlParams.delete('transaction_status');
            urlParams.delete('payment_type');
            urlParams.delete('fraud_status');
            window.history.replaceState({}, document.title, `${window.location.pathname}${urlParams.toString() ? '?' + urlParams.toString() : ''}`);

            if (midtransStatus === 'success') {
                toast.success('Pembayaran Midtrans Berhasil!', {
                    description: `Transaksi ${midtransOrderId} telah selesai.`,
                    duration: 5000,
                });
            } else if (midtransStatus === 'error') {
                toast.error('Pembayaran Midtrans Gagal', {
                    description: `Transaksi ${midtransOrderId} dibatalkan atau gagal.`,
                    duration: 5000,
                });
            } else if (midtransStatus === 'pending') {
                toast.info('Pembayaran Midtrans Pending', {
                    description: `Transaksi ${midtransOrderId} sedang menunggu pembayaran.`,
                    duration: 5000,
                });
            } else {
                toast.info('Status Pembayaran Midtrans', {
                    description: `Status: ${midtransStatus} untuk Order ID: ${midtransOrderId}.`,
                    duration: 5000,
                });
            }

            // Reset cart and form fields only after handling Midtrans redirect
            setCartItems([]);
            setAmountPaid(0);
            setSelectedCustomerId(null);
            setSelectedMejaId(null);
            setPaymentMethod('cash');
            setTransactionType('dine_in');

            // Reload data from the server to get updated stock and transactions
            router.reload({
                only: ['products', 'mejas', 'customers', 'settings', 'kategoris', 'latestTransactions'],
                onSuccess: (page) => {
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
    }, []);

    const filteredProducts = useMemo(() => {
        return products.filter((product) => {
            const matchesSearch = product.nama_produk.toLowerCase().includes(searchTerm.toLowerCase());
            const matchesCategory = selectedCategoryId === null || product.kategori_id === selectedCategoryId;
            return matchesSearch && matchesCategory;
        });
    }, [products, searchTerm, selectedCategoryId]);

    const subTotal = useMemo(() => {
        return cartItems.reduce((acc, item) => acc + (item.harga_jual || 0) * item.qty, 0);
    }, [cartItems]);

    const ppnAmount = useMemo(() => {
        return (subTotal * (settings.ppn || 0)) / 100;
    }, [subTotal, settings.ppn]);

    const biayaLayanan = useMemo(() => {
        return transactionType === 'dine_in' ? settings.biaya_lainnya || 0 : 0;
    }, [transactionType, settings.biaya_lainnya]);

    const totalAmount = useMemo(() => {
        return (subTotal || 0) + (ppnAmount || 0) + (biayaLayanan || 0);
    }, [subTotal, ppnAmount, biayaLayanan]);

    const change = useMemo(() => {
        return paymentMethod === 'cash' ? (amountPaid || 0) - (totalAmount || 0) : 0;
    }, [paymentMethod, amountPaid, totalAmount]);

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

    const getProductQuantityInCart = (productId: number) => {
        const item = cartItems.find((item) => item.id === productId);
        return item ? item.qty : 0;
    };

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

        // Add this check for Midtrans payment method
        if (paymentMethod === 'midtrans' && !isSnapLoaded) {
            toast.error('Midtrans belum siap', {
                description: 'Snap.js sedang dimuat. Mohon tunggu sebentar atau refresh halaman.',
            });
            return;
        }

        setIsLoading(true);

        try {
            const payload = {
                cartItems: cartItems.map((item) => ({ id: item.id, qty: item.qty })),
                paymentMethod,
                amountPaid: paymentMethod === 'cash' ? amountPaid : null, // Only send amountPaid for cash
                customerId: selectedCustomerId,
                mejaId: transactionType === 'dine_in' ? selectedMejaId : null,
                type: transactionType,
            };

            const response = await axios.post(route('pos.process_sale'), payload);
            const data = response.data;

            if (data.success) {
                // Always reset cart and form fields after a successful sale initiation
                setCartItems([]);
                setAmountPaid(0);
                setSelectedCustomerId(null);
                setSelectedMejaId(null);
                setPaymentMethod('cash');
                setTransactionType('dine_in');

                if (paymentMethod === 'cash') {
                    setSuccessInvoice(data.invoice as InvoiceForPopUp);
                    setSuccessChange(data.change);
                    setIsSuccessModalOpen(true);

                    toast.success('Pembayaran Tunai Berhasil!', {
                        description: `Transaksi selesai. Kembalian: ${new Intl.NumberFormat('id-ID').format(data.change || 0)}.`,
                        duration: 3000,
                    });
                    // Reload data after cash transaction to update stocks and history
                    router.reload({
                        only: ['products', 'mejas', 'latestTransactions'],
                        onSuccess: (page) => {
                            const typedPageProps = page.props as unknown as CashierPageProps;
                            setProducts(typedPageProps.products);
                            setMejas(typedPageProps.mejas);
                            setTransactionHistory(typedPageProps.latestTransactions);
                        },
                    });
                } else if (paymentMethod === 'midtrans') {
                    toast.info('Mengarahkan ke Midtrans...', {
                        description: 'Harap tunggu, Anda akan diarahkan ke halaman pembayaran atau pop-up akan muncul.',
                        duration: 3000,
                    });

                    if (data.snapToken && window.snap && isSnapLoaded) {
                        window.snap.pay(data.snapToken, {
                            onSuccess: function (result) {
                                console.log('Midtrans Payment Success:', result);
                                toast.success('Pembayaran Berhasil!', {
                                    description: `Transaksi ${data.invoiceNumber} selesai.`,
                                });
                                // Reload data to update stocks and history after successful payment
                                router.reload({
                                    only: ['products', 'mejas', 'latestTransactions'],
                                });
                            },
                            onPending: function (result) {
                                console.log('Midtrans Payment Pending:', result);
                                toast.info('Pembayaran Tertunda', {
                                    description: `Transaksi ${data.invoiceNumber} sedang menunggu pembayaran.`,
                                });
                                // Reload data to update history for pending status
                                router.reload({
                                    only: ['products', 'mejas', 'latestTransactions'],
                                });
                            },
                            onError: function (result) {
                                console.log('Midtrans Payment Error:', result);
                                toast.error('Pembayaran Gagal', {
                                    description: `Transaksi ${data.invoiceNumber} gagal.`,
                                });
                                // Reload data to update history for failed status
                                router.reload({
                                    only: ['products', 'mejas', 'latestTransactions'],
                                });
                            },
                            onClose: function () {
                                console.log('Customer closed Midtrans pop-up without finishing payment.');
                                toast.warning('Pembayaran Dibatalkan', {
                                    description: 'Anda menutup jendela pembayaran.',
                                });
                                // Optionally reload here if you want to update transaction status immediately if it was pending
                                router.reload({
                                    only: ['products', 'mejas', 'latestTransactions'],
                                });
                            },
                        });
                    } else if (data.redirectUrl) {
                        // Fallback or explicit redirect option (if backend provides redirectUrl directly)
                        window.location.href = data.redirectUrl;
                    } else {
                        toast.error('Gagal Memuat Pembayaran Midtrans', {
                            description: 'Snap token tidak tersedia atau Snap.js belum dimuat dengan benar.',
                        });
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

    const handlePrintFromModal = () => {
        if (successInvoice) {
            const printUrl = route('penjualan.print', successInvoice.id);
            window.open(printUrl, '_blank');
        }
    };

    return (
        <CashierLayout>
            <Head title="Kasir POS" />

            <CashierNavbar
                settings={settings}
                showHistory={showHistory}
                setShowHistory={setShowHistory}
                isMobileMenuOpen={isMobileMenuOpen}
                setIsMobileMenuOpen={setIsMobileMenuOpen}
                auth={initialAuth}
                onLogout={handleLogout}
            />

            {isMobileMenuOpen && (
                <MobileMenu
                    showHistory={showHistory}
                    setShowHistory={setShowHistory}
                    setIsMobileMenuOpen={setIsMobileMenuOpen}
                    auth={initialAuth}
                    onLogout={handleLogout}
                />
            )}

            <div className="flex flex-1 overflow-auto bg-[#F5F5F5] p-4 lg:p-6">
                {!showHistory ? (
                    <div className="grid w-full grid-cols-1 gap-6 lg:grid-cols-3">
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
                        initialInvoiceSearchTerm={currentInertiaPageProps.filters?.invoice_number || ''}
                    />
                )}
            </div>
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
                            onClick={() => setIsSuccessModalOpen(false)}
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
