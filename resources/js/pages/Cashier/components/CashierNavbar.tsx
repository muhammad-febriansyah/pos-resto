// src/Components/CashierNavbar.tsx

import { Button } from '@/components/ui/button';
import { Setting } from '@/types/setting';
import { User } from '@/types/user';
import { Menu, X } from 'lucide-react'; // Import LogOut here
import React from 'react';

interface CashierNavbarProps {
    showHistory: boolean;
    setShowHistory: (show: boolean) => void;
    isMobileMenuOpen: boolean;
    setIsMobileMenuOpen: (isOpen: boolean) => void;
    auth: { user: User };
    onLogout: () => void; // Add onLogout prop
    settings: Setting;
}

const CashierNavbar: React.FC<CashierNavbarProps> = ({ showHistory, setShowHistory, isMobileMenuOpen, setIsMobileMenuOpen, auth, settings }) => {
    const name = auth.user.name;
    const initialsUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=0D8ABC&color=fff&size=128`;

    return (
        <nav className="sticky top-0 z-50 w-full bg-white shadow-sm">
            <div className="mx-auto flex h-16 items-center justify-between px-4 lg:px-6">
                <div className="flex items-center">
                    {/* <LayoutPanelTop className="mr-3 h-8 w-8 text-biru" /> */}
                    <img src={`/storage/${settings.logo}`} className="mr-3 h-18 w-20 object-cover" title={settings.site_name} alt="" />
                    {/* <h1 className="text-2xl font-semibold text-gray-900">{settings.site_name}</h1> */}
                </div>

                {/* Desktop Navigation Links */}
                <div className="hidden items-center space-x-6 lg:flex">
                    <Button
                        variant="ghost"
                        className={`relative flex items-center text-base font-semibold transition-colors duration-200 ${
                            !showHistory ? 'text-indigo-600' : 'text-gray-500 hover:text-gray-800'
                        } after:absolute after:bottom-0 after:left-0 after:h-[3px] after:w-full after:rounded-full after:bg-indigo-500 after:transition-all after:duration-300 ${
                            !showHistory ? 'after:opacity-100' : 'after:opacity-0'
                        }`}
                        onClick={() => setShowHistory(false)}
                    >
                        Pesan
                    </Button>

                    <Button
                        variant="ghost"
                        className={`relative flex items-center text-base font-semibold transition-colors duration-200 ${
                            showHistory ? 'text-indigo-600' : 'text-gray-500 hover:text-gray-800'
                        } after:absolute after:bottom-0 after:left-0 after:h-[3px] after:w-full after:rounded-full after:bg-indigo-500 after:transition-all after:duration-300 ${
                            showHistory ? 'after:opacity-100' : 'after:opacity-0'
                        }`}
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
        </nav>
    );
};

export default CashierNavbar;
