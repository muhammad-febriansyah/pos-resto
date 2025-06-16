// src/Components/columns.ts
import { Button } from '@/components/ui/button';
import { Penjualan } from '@/types/penjualan';
import { ColumnDef } from '@tanstack/react-table';
import { Printer } from 'lucide-react';

export const columns = (handlePrint: (trx: Penjualan) => void): ColumnDef<Penjualan>[] => [
    {
        id: 'no',
        header: 'No',
        cell: ({ row }) => row.index + 1,
        meta: { align: 'center' },
    },
    {
        accessorKey: 'invoice_number',
        header: 'Invoice',
        cell: ({ row }) => <span className="font-medium">{row.original.invoice_number}</span>,
    },
    {
        accessorKey: 'created_at',
        header: 'Tanggal',
        cell: ({ row }) => new Date(row.original.created_at).toLocaleString('id-ID'),
    },
    {
        accessorKey: 'customer',
        header: 'Pelanggan',
        cell: ({ row }) => row.original.customer?.name ?? 'Guest',
    },
    {
        accessorKey: 'total',
        header: 'Total',
        cell: ({ row }) => new Intl.NumberFormat('id-ID').format(row.original.total),
        meta: { align: 'right' },
    },
    {
        accessorKey: 'payment_method',
        header: 'Metode',
        cell: ({ row }) => <span className="capitalize">{row.original.payment_method}</span>,
    },
    {
        accessorKey: 'type',
        header: 'Tipe',
        cell: ({ row }) => <span className="capitalize">{row.original.type?.replace('_', ' ') ?? 'N/A'}</span>,
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const status = row.original.status;
            const color =
                status === 'paid'
                    ? 'bg-emerald-100 text-emerald-700'
                    : status === 'pending'
                      ? 'bg-yellow-100 text-yellow-700'
                      : 'bg-red-100 text-red-700';

            return <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${color}`}>{status}</span>;
        },
    },
    {
        id: 'actions',
        header: 'Aksi',
        cell: ({ row }) => (
            <Button variant="ghost" size="icon" className="text-biru hover:bg-biru/10" onClick={() => handlePrint(row.original)}>
                <Printer className="h-5 w-5" />
            </Button>
        ),
    },
];
