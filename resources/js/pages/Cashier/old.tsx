// resources/js/Pages/Cashier/Index.tsx
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router, usePage } from '@inertiajs/react'; // router is still here for potential other Inertia uses, but not for process_sale
import axios from 'axios'; // IMPORT AXIOS DITAMBAHKAN
import React, { SVGProps, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

// --- Import Semua Ikon Lucide yang Digunakan ---
// PENTING: Setiap string nama ikon di kolom 'icon' DB Anda (contoh: 'Coffee', 'Utensils')
// harus diimpor di sini. Jika tidak diimpor, ikon tidak akan muncul.
// JIKA ANDA MENDAPATKAN ERROR "does not provide an export named 'X'", HAPUS 'X' DARI SINI
// DAN VERIFIKASI NAMA IKON DI LUCIDE.DEV, KEMUDIAN UPDATE DB ANDA.
import {
    Beer,
    Cake,
    Candy,
    Carrot,
    Coffee,
    Fish,
    GlassWater, // Bisa digunakan untuk minuman umum/jus
    IceCream,
    Loader2,
    Milk,
    Minus,
    Package, // Untuk "Semua Produk" atau ikon default
    Plus,
    Popcorn,
    Sandwich,
    Soup,
    Utensils, // Ikon umum untuk makanan
    X,
} from 'lucide-react';

// --- Interface Data ---
interface Product {
    id: number;
    nama_produk: string;
    harga_jual: number;
    stok: number;
    image?: string;
    kategori_id: number;
}

interface Customer {
    id: number;
    name: string;
    email: string;
    phone?: string;
}

interface Meja {
    id: number;
    nama: string;
    status: 'tersedia' | 'dipakai';
}

interface Setting {
    ppn: number;
    biaya_layanan_default: number;
}

interface Kategori {
    id: number;
    kategori: string;
    slug: string;
    icon?: string;
}

interface CartItem extends Product {
    qty: number;
}

interface CashierProps {
    products: Product[];
    mejas: Meja[];
    customers: Customer[];
    settings: Setting;
    kategoris: Kategori[];
}

// --- Peta Ikon Lucide ---
// Ini memetakan string nama ikon (dari DB) ke komponen React yang sebenarnya.
// PENTING: Key di objek ini harus PERSIS sama (case-sensitive) dengan string di kolom 'icon' DB.
const LucideIconMap: { [key: string]: React.ElementType<SVGProps<SVGSVGElement>> } = {
    Package: Package,
    Coffee: Coffee,
    Utensils: Utensils,
    Popcorn: Popcorn,
    GlassWater: GlassWater,
    Milk: Milk,
    Sandwich: Sandwich,
    Fish: Fish,
    Soup: Soup,
    Cake: Cake,
    IceCream: IceCream,
    Candy: Candy,
    Beer: Beer,
    Carrot: Carrot,
    // --- Tambahkan mapping untuk ikon spesifik lainnya yang Anda gunakan di DB ---
    // Contoh:
    // Apple: Apple,
    // Croissant: Croissant,
    // Donut: Donut,
    // Jika di DB Anda ada kategori "Burger" dan Anda memutuskan menggunakan ikon "Utensils" untuk itu:
    // Burger: Utensils,
    // Jika di DB Anda ada kategori "Pizza" dan Anda memutuskan menggunakan ikon "PizzaSlice" untuk itu:
    // Pizza: PizzaSlice, // Perlu import PizzaSlice di atas
};

// --- Path Placeholder Image ---
// Pastikan file placeholder.svg ada di public/images/
const PLACEHOLDER_IMAGE_PATH = '/images/placeholder.svg';

// --- Komponen Cashier ---
const Cashier: React.FC<CashierProps> = ({ products, mejas, customers, settings, kategoris }) => {
    const { auth } = usePage().props as { auth: { user: any } };

    // --- State Lokal ---
    const [cartItems, setCartItems] = useState<CartItem[]>([]);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
    const [selectedCustomerId, setSelectedCustomerId] = useState<string | null>(null);
    const [selectedMejaId, setSelectedMejaId] = useState<string | null>(null);
    const [paymentMethod, setPaymentMethod] = useState<'cash' | 'duitku'>('cash');
    const [amountPaid, setAmountPaid] = useState<number>(0);
    const [transactionType, setTransactionType] = useState<'dine_in' | 'take_away' | 'delivery'>('dine_in');
    const [isLoading, setIsLoading] = useState<boolean>(false);

    // --- Efek untuk menangani redirect Duitku ---
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
            setCartItems([]);
            setAmountPaid(0);
            setSelectedCustomerId(null);
            setSelectedMejaId(null);
            setPaymentMethod('cash');
            setTransactionType('dine_in');
        }
    }, []);

    // --- Filter Produk Berdasarkan Pencarian dan Kategori ---
    const filteredProducts = useMemo(() => {
        return products.filter((product) => {
            const matchesSearch = product.nama_produk.toLowerCase().includes(searchTerm.toLowerCase());
            const matchesCategory = selectedCategoryId === null || product.kategori_id === selectedCategoryId;
            return matchesSearch && matchesCategory && product.stok > 0;
        });
    }, [products, searchTerm, selectedCategoryId]);

    // --- Perhitungan Ringkasan Pesanan (Menggunakan useMemo untuk optimasi) ---
    const subTotal = useMemo(() => {
        return cartItems.reduce((acc, item) => acc + (item.harga_jual || 0) * item.qty, 0);
    }, [cartItems]);

    const ppnAmount = useMemo(() => {
        return (subTotal * (settings.ppn || 0)) / 100;
    }, [subTotal, settings.ppn]);

    const biayaLayanan = useMemo(() => {
        return transactionType === 'dine_in' ? settings.biaya_layanan_default || 0 : 0;
    }, [transactionType, settings.biaya_layanan_default]);

    const totalAmount = useMemo(() => {
        return (subTotal || 0) + (ppnAmount || 0) + (biayaLayanan || 0);
    }, [subTotal, ppnAmount, biayaLayanan]);

    const change = useMemo(() => {
        return paymentMethod === 'cash' ? (amountPaid || 0) - (totalAmount || 0) : 0;
    }, [paymentMethod, amountPaid, totalAmount]);

    // --- Fungsi Penambahan dan Pengelolaan Keranjang ---
    const addProductToCart = (product: Product) => {
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
            const data = response.data; // Axios wraps the response in a 'data' property

            if (data.success) {
                if (paymentMethod === 'cash') {
                    setCartItems([]);
                    setAmountPaid(0);
                    setSelectedCustomerId(null);
                    setSelectedMejaId(null);
                    setPaymentMethod('cash');
                    toast.success('Pembayaran Berhasil!', {
                        description: `Transaksi selesai. Kembalian: ${new Intl.NumberFormat('id-ID').format(data.change)}.`,
                    });
                    router.reload(); // Reload the page to reset the state
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
            // Handle Axios errors
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
            // Ensure loading state is reset, especially for cash payments
            // Duitku redirect handles the reset for Duitku payments
            if (paymentMethod === 'cash' || !isLoading) {
                // Ensure isLoading is true before setting to false for duitku to prevent premature reset
                setIsLoading(false);
            }
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

    // --- Render Komponen ---
    return (
        <div className="min-h-screen bg-gray-50 p-6 font-sans">
            <Head title="Kasir POS" />

            <h1 className="mb-8 text-center text-3xl font-extrabold text-gray-900">Sistem Kasir Modern</h1>

            <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                {/* Bagian Daftar Produk */}
                <Card className="border-none bg-white shadow-lg lg:col-span-2">
                    <CardHeader className="pb-4">
                        <CardTitle className="text-2xl font-bold text-gray-800">Pilih Produk</CardTitle>
                        <Input
                            placeholder="Cari produk..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="mt-4 rounded-md border-gray-300 p-2 focus:border-blue-500 focus:ring-blue-500"
                        />
                        {/* Filter Kategori (Toggle Buttons) */}
                        <div className="mt-4 flex flex-wrap gap-2">
                            <Button
                                variant={selectedCategoryId === null ? 'default' : 'outline'}
                                onClick={() => setSelectedCategoryId(null)}
                                className="flex-shrink-0"
                            >
                                <Package className="mr-2 h-4 w-4" /> Semua Produk
                            </Button>
                            {kategoris.map((kategori) => {
                                const IconComponent = kategori.icon && LucideIconMap[kategori.icon] ? LucideIconMap[kategori.icon] : null;

                                return (
                                    <Button
                                        key={kategori.id}
                                        variant={selectedCategoryId === kategori.id ? 'default' : 'outline'}
                                        onClick={() => setSelectedCategoryId(kategori.id)}
                                        className="flex-shrink-0"
                                    >
                                        {IconComponent && <IconComponent className="mr-2 h-4 w-4" />}
                                        {kategori.kategori}
                                    </Button>
                                );
                            })}
                        </div>
                    </CardHeader>
                    <CardContent className="custom-scrollbar grid max-h-[calc(100vh-360px)] grid-cols-2 gap-4 overflow-y-auto p-4 md:grid-cols-3 lg:grid-cols-4">
                        {filteredProducts.length === 0 ? (
                            <p className="col-span-full py-10 text-center text-gray-500">Produk tidak ditemukan atau stok habis.</p>
                        ) : (
                            filteredProducts.map((product) => (
                                <Card
                                    key={product.id}
                                    className="transform cursor-pointer rounded-lg border border-gray-200 bg-white transition-all duration-200 ease-in-out hover:-translate-y-1 hover:shadow-xl"
                                    onClick={() => addProductToCart(product)}
                                >
                                    <CardContent className="flex h-full flex-col items-center p-3 text-center">
                                        <img
                                            src={product.image ? `/storage/${product.image}` : PLACEHOLDER_IMAGE_PATH}
                                            alt={product.nama_produk}
                                            className="mb-3 h-32 w-full rounded-md border border-gray-100 object-cover"
                                        />
                                        <div className="flex w-full flex-grow flex-col justify-end">
                                            <h3 className="mb-1 w-full truncate px-1 text-base font-semibold text-gray-800">{product.nama_produk}</h3>
                                            <p className="mb-2 text-xs text-gray-500">Stok: {product.stok}</p>
                                            <p className="text-lg font-bold text-blue-600">
                                                {new Intl.NumberFormat('id-ID').format(product.harga_jual || 0)}
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </CardContent>
                </Card>

                {/* Bagian Ringkasan Keranjang & Pembayaran */}
                <Card className="border-none bg-white shadow-lg lg:col-span-1">
                    <CardHeader className="pb-4">
                        <CardTitle className="text-2xl font-bold text-gray-800">Rincian Pesanan & Pembayaran</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {/* Pilih Pelanggan */}
                        <div>
                            <Label htmlFor="customer-select" className="mb-2 block text-sm font-medium text-gray-700">
                                Pelanggan
                            </Label>
                            <Select
                                onValueChange={(value) => setSelectedCustomerId(value === 'guest_option' ? null : value)}
                                value={selectedCustomerId || 'guest_option'}
                            >
                                <SelectTrigger id="customer-select" className="h-10 w-full px-3 text-base">
                                    <SelectValue placeholder="Pilih Pelanggan (Opsional)" />
                                </SelectTrigger>
                                <SelectContent className="max-h-60 overflow-y-auto">
                                    <SelectItem value="guest_option" className="px-3 py-2 text-base">
                                        Guest
                                    </SelectItem>
                                    {customers.map((customer) => (
                                        <SelectItem key={customer.id} value={String(customer.id)} className="px-3 py-2 text-base">
                                            {customer.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Pilih Tipe Transaksi (Toggle Buttons) */}
                        <div>
                            <Label className="mb-2 block text-sm font-medium text-gray-700">Tipe Transaksi</Label>
                            <div className="flex space-x-2">
                                <Button
                                    variant={transactionType === 'dine_in' ? 'default' : 'outline'}
                                    onClick={() => setTransactionType('dine_in')}
                                    className="h-10 flex-1 text-base"
                                >
                                    Dine In
                                </Button>
                                <Button
                                    variant={transactionType === 'take_away' ? 'default' : 'outline'}
                                    onClick={() => setTransactionType('take_away')}
                                    className="h-10 flex-1 text-base"
                                >
                                    Take Away
                                </Button>
                                <Button
                                    variant={transactionType === 'delivery' ? 'default' : 'outline'}
                                    onClick={() => setTransactionType('delivery')}
                                    className="h-10 flex-1 text-base"
                                >
                                    Delivery
                                </Button>
                            </div>
                        </div>

                        {/* Pilih Meja (Hanya muncul jika tipe transaksi 'Dine In') */}
                        {transactionType === 'dine_in' && (
                            <div>
                                <Label htmlFor="meja-select" className="mb-2 block text-sm font-medium text-gray-700">
                                    Meja
                                </Label>
                                <Select
                                    onValueChange={(value) => setSelectedMejaId(value === 'no_table_option' ? null : value)}
                                    value={selectedMejaId || 'no_table_option'}
                                >
                                    <SelectTrigger id="meja-select" className="h-10 w-full px-3 text-base">
                                        <SelectValue placeholder="Pilih Meja (Opsional)" />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-60 overflow-y-auto">
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

                        {/* Tabel Item di Keranjang */}
                        <div>
                            <h3 className="mb-3 text-lg font-semibold text-gray-800">Item Keranjang</h3>
                            <div className="custom-scrollbar max-h-48 overflow-y-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="bg-gray-50">
                                            <TableHead className="w-[40%]">Produk</TableHead>
                                            <TableHead className="w-[20%] text-center">Qty</TableHead>
                                            <TableHead className="w-[30%] text-right">Subtotal</TableHead>
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
                                                <TableRow key={item.id}>
                                                    <TableCell className="pr-1 text-sm font-medium">
                                                        {item.nama_produk} <br />
                                                        <span className="text-xs text-gray-500">
                                                            {new Intl.NumberFormat('id-ID').format(item.harga_jual || 0)}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell className="text-center">
                                                        <div className="flex items-center justify-center space-x-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-7 w-7"
                                                                onClick={() => updateQuantity(item.id, -1)}
                                                                disabled={item.qty <= 1}
                                                            >
                                                                <Minus className="h-4 w-4" />
                                                            </Button>
                                                            <span className="text-sm font-semibold">{item.qty}</span>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-7 w-7"
                                                                onClick={() => updateQuantity(item.id, 1)}
                                                                disabled={item.qty >= item.stok}
                                                            >
                                                                <Plus className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right text-sm font-semibold">
                                                        {new Intl.NumberFormat('id-ID').format(item.qty * (item.harga_jual || 0))}
                                                    </TableCell>
                                                    <TableCell className="text-center">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-7 w-7 text-red-500 hover:bg-red-50 hover:text-red-600"
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

                        {/* Rincian Total Pembayaran */}
                        <div className="space-y-2 border-t border-gray-200 pt-4 text-right">
                            <div className="flex justify-between text-base text-gray-700">
                                <span>Sub Total:</span>
                                <span>{new Intl.NumberFormat('id-ID').format(subTotal)}</span>
                            </div>
                            <div className="flex justify-between text-base text-gray-700">
                                <span>PPN ({settings.ppn || 0}%):</span>
                                <span>{new Intl.NumberFormat('id-ID').format(ppnAmount)}</span>
                            </div>
                            <div className="flex justify-between text-base text-gray-700">
                                <span>Biaya Layanan:</span>
                                <span>{new Intl.NumberFormat('id-ID').format(biayaLayanan)}</span>
                            </div>
                            <div className="mt-3 flex justify-between border-t-2 border-blue-200 pt-3 text-2xl font-extrabold text-blue-700">
                                <span>TOTAL:</span>
                                <span>{new Intl.NumberFormat('id-ID').format(totalAmount)}</span>
                            </div>
                        </div>

                        {/* Metode Pembayaran (Toggle Buttons) */}
                        <div>
                            <h3 className="mb-3 text-lg font-semibold text-gray-800">Metode Pembayaran</h3>
                            <div className="flex space-x-2">
                                <Button
                                    variant={paymentMethod === 'cash' ? 'default' : 'outline'}
                                    onClick={() => setPaymentMethod('cash')}
                                    className="h-10 flex-1 text-base"
                                >
                                    Tunai
                                </Button>
                                <Button
                                    variant={paymentMethod === 'duitku' ? 'default' : 'outline'}
                                    onClick={() => setPaymentMethod('duitku')}
                                    className="h-10 flex-1 text-base"
                                >
                                    Duitku
                                </Button>
                            </div>

                            {paymentMethod === 'cash' && (
                                <div className="mt-4 rounded-md border border-gray-200 bg-gray-50 p-4">
                                    <Label htmlFor="amount-paid" className="mb-2 block text-sm font-medium text-gray-700">
                                        Jumlah Pembayaran Tunai
                                    </Label>
                                    <Input
                                        id="amount-paid"
                                        type="number"
                                        placeholder="0"
                                        value={amountPaid === 0 ? '' : amountPaid}
                                        onChange={(e) => setAmountPaid(parseFloat(e.target.value) || 0)}
                                        className="h-10 w-full px-3 text-base focus:border-blue-500 focus:ring-blue-500"
                                    />
                                    <p className="mt-3 text-sm font-medium text-gray-600">
                                        Kembalian: <span className="font-bold text-blue-600">{new Intl.NumberFormat('id-ID').format(change)}</span>
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Tombol Aksi */}
                        <div className="mt-6 space-y-3">
                            <Button
                                className="h-12 w-full bg-blue-600 text-lg font-semibold transition-colors duration-200 hover:bg-blue-700"
                                onClick={handleProcessPayment}
                                disabled={cartItems.length === 0 || (paymentMethod === 'cash' && amountPaid < totalAmount) || isLoading}
                            >
                                {isLoading ? <Loader2 className="mr-3 h-5 w-5 animate-spin" /> : null}
                                Proses Pembayaran
                            </Button>
                            <Button
                                className="h-10 w-full border-red-500 text-base font-semibold text-red-500 transition-colors duration-200 hover:bg-red-50 hover:text-red-600"
                                variant="outline"
                                onClick={handleCancelTransaction}
                                disabled={isLoading}
                            >
                                Batalkan Transaksi
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
            {/* Custom scrollbar style */}
            <style>{`
                .custom-scrollbar::-webkit-scrollbar {
                    width: 8px;
                }
                .custom-scrollbar::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 10px;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb {
                    background: #cbd5e1; /* gray-300 */
                    border-radius: 10px;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                    background: #94a3b8; /* gray-400 */
                }
            `}</style>
        </div>
    );
};

export default Cashier;
