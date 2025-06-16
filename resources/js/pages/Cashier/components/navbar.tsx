import { Button } from '@/components/ui/button';
import { Setting } from '@/types/setting';
import { useState } from 'react';
interface Props {
    settings: Setting;
}
export default function Navbar({ settings }: Props) {
    const [showHistory, setShowHistory] = useState(false);
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false); // State for mobile navbar menu

    return (
        <nav className="sticky top-0 z-50 w-full bg-white shadow-sm">
            <div className="mx-auto flex h-16 items-center justify-between px-4 lg:px-6">
                <div className="flex items-center">
                    <img src={`/storage/${settings.logo}`} className="mr-3 h-20 w-20 object-cover" alt="" />
                    <h1 className="text-2xl font-extrabold text-gray-900">{settings.site_name}</h1>
                </div>

                {/* Desktop Navigation Links */}
                <div className="hidden items-center space-x-6 lg:flex">
                    <Button
                        variant="ghost"
                        className={`flex items-center text-lg font-medium transition-colors duration-200 ${!showHistory ? 'rounded-xl bg-biru text-white hover:text-indigo-700' : 'text-gray-600 hover:text-gray-800'}`}
                        onClick={() => setShowHistory(false)}
                    >
                        Pesan
                    </Button>
                    <Button
                        variant="ghost"
                        className={`flex items-center text-lg font-medium transition-colors duration-200 ${showHistory ? 'text-biru hover:text-indigo-700' : 'text-gray-600 hover:text-gray-800'}`}
                        onClick={() => setShowHistory(true)}
                    >
                        Aktifitas
                    </Button>
                </div>

                {/* User Avatar and Mobile Menu Button */}
                <div className="flex items-center space-x-4">
                    {/* User Avatar (Desktop) */}
                    <div className="hidden items-center space-x-2 lg:flex">
                        <img
                            src={auth.user.avatar ? `/storage/${auth.user.avatar}` : initialsUrl}
                            alt={auth.user.name}
                            className="h-9 w-9 rounded-full border-2 border-indigo-400 object-cover shadow-sm"
                        />
                        <span className="text-base font-semibold text-gray-700">{auth.user.name.split(' ')[0]}</span>
                    </div>

                    {/* Mobile Menu Button */}
                    <Button variant="ghost" size="icon" className="lg:hidden" onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}>
                        {isMobileMenuOpen ? <X className="h-6 w-6 text-gray-600" /> : <Menu className="h-6 w-6 text-gray-600" />}
                    </Button>
                </div>
            </div>

            {/* Mobile Menu */}
            {isMobileMenuOpen && (
                <div className="border-t border-gray-200 py-4 lg:hidden">
                    <div className="flex flex-col items-start space-y-2 px-4">
                        <Button
                            variant="ghost"
                            className={`w-full justify-start text-lg font-medium ${!showHistory ? 'text-biru' : 'text-gray-600'}`}
                            onClick={() => {
                                setShowHistory(false);
                                setIsMobileMenuOpen(false);
                            }}
                        >
                            <ShoppingCart className="mr-2 h-5 w-5" /> Kasir
                        </Button>
                        <Button
                            variant="ghost"
                            className={`w-full justify-start text-lg font-medium ${showHistory ? 'text-biru' : 'text-gray-600'}`}
                            onClick={() => {
                                setShowHistory(true);
                                setIsMobileMenuOpen(false);
                            }}
                        >
                            <History className="mr-2 h-5 w-5" /> Riwayat Transaksi
                        </Button>
                        {/* User Info for Mobile */}
                        <div className="mt-2 flex w-full items-center space-x-2 border-t border-gray-200 pt-4">
                            <img
                                src={auth.user.avatar ? `/storage/${auth.user.avatar}` : initialsUrl}
                                alt={auth.user.name}
                                className="h-9 w-9 rounded-full border-2 border-indigo-400 object-cover shadow-sm"
                            />
                            <span className="text-base font-semibold text-gray-700">{auth.user.name}</span>
                        </div>
                        {/* Example Logout button in mobile menu */}
                        <Button
                            variant="ghost"
                            className="w-full justify-start text-red-500 hover:bg-red-50 hover:text-red-600"
                            onClick={() => router.post(route('logout'))} // Assuming you have a logout route
                        >
                            <LogOut className="mr-2 h-5 w-5" /> Keluar
                        </Button>
                    </div>
                </div>
            )}
        </nav>
    );
}
