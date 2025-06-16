// src/Components/MobileMenu.tsx

import { Button } from '@/components/ui/button';
import { User } from '@/types/user';
import { History, LogOut, ShoppingCart } from 'lucide-react';
import React from 'react';

interface MobileMenuProps {
    showHistory: boolean;
    setShowHistory: (show: boolean) => void;
    setIsMobileMenuOpen: (isOpen: boolean) => void;
    auth: { user: User };
    onLogout: () => void; // Add onLogout prop
}

const MobileMenu: React.FC<MobileMenuProps> = ({
    showHistory,
    setShowHistory,
    setIsMobileMenuOpen,
    auth,
    onLogout, // Destructure onLogout
}) => {
    const name = auth.user.name;
    const initialsUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=0D8ABC&color=fff&size=128`;

    return (
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
                {/* Logout button in mobile menu */}
                <Button
                    variant="ghost"
                    className="w-full justify-start text-red-500 hover:bg-red-50 hover:text-red-600"
                    onClick={onLogout} // Use the onLogout prop
                >
                    <LogOut className="mr-2 h-5 w-5" /> Keluar
                </Button>
            </div>
        </div>
    );
};

export default MobileMenu;
