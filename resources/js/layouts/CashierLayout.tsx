import React from 'react';

interface Props {
    children?: React.ReactNode;
}
export default function CashierLayout({ children }: Props) {
    return (
        <div className="overflow-x-hidden" id="home">
            {children}
        </div>
    );
}
