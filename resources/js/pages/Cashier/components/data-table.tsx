import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    ColumnDef,
    ColumnFiltersState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useEffect, useState } from 'react'; // Import useEffect

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    // --- FIX: Add the new prop to the interface ---
    initialInvoiceSearchTerm?: string; // Make it optional in case it's not always provided
}

export function DataTable<TData, TValue>({ columns, data, initialInvoiceSearchTerm }: DataTableProps<TData, TValue>) {
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        onColumnFiltersChange: setColumnFilters,
        getFilteredRowModel: getFilteredRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        state: {
            columnFilters,
        },
    });

    // --- FIX: Use useEffect to apply the initial filter ---
    useEffect(() => {
        if (initialInvoiceSearchTerm && table.getColumn('invoice_number')) {
            // Assuming a column with 'invoice_number' ID exists
            table.getColumn('invoice_number')?.setFilterValue(initialInvoiceSearchTerm);
        } else if (initialInvoiceSearchTerm && table.getColumn('customer')) {
            // Fallback to customer if invoice_number not found
            table.getColumn('customer')?.setFilterValue(initialInvoiceSearchTerm);
        }
    }, [initialInvoiceSearchTerm, table]); // Re-run when initialInvoiceSearchTerm or table instance changes

    return (
        <div className="rounded-md border">
            <div className="flex items-center p-4">
                <Input
                    placeholder="Cari Invoice atau Customer..." // Updated placeholder
                    // --- FIX: Prioritize searching by 'invoice_number' first ---
                    value={
                        (table.getColumn('invoice_number')?.getFilterValue() as string) ??
                        (table.getColumn('customer')?.getFilterValue() as string) ??
                        ''
                    }
                    onChange={(event) => {
                        // Clear existing filters for both to avoid conflicts
                        table.getColumn('invoice_number')?.setFilterValue(undefined);
                        table.getColumn('customer')?.setFilterValue(undefined);

                        // Apply filter based on input
                        // You'll need to decide which column to filter by default if both exist
                        // For simplicity, let's assume filtering 'invoice_number' if it exists, otherwise 'customer'
                        if (table.getColumn('invoice_number')) {
                            table.getColumn('invoice_number')?.setFilterValue(event.target.value);
                        } else if (table.getColumn('customer')) {
                            table.getColumn('customer')?.setFilterValue(event.target.value);
                        }
                    }}
                    className="max-w-sm"
                />
            </div>
            <Table>
                <TableHeader>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRow key={headerGroup.id}>
                            {headerGroup.headers.map((header) => (
                                <TableHead key={header.id} className="text-gray-600">
                                    {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                </TableHead>
                            ))}
                        </TableRow>
                    ))}
                </TableHeader>
                <TableBody>
                    {table.getRowModel().rows.map((row) => (
                        <TableRow key={row.id} className="hover:bg-gray-50">
                            {row.getVisibleCells().map((cell) => (
                                <TableCell key={cell.id} className="text-gray-700">
                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                </TableCell>
                            ))}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <div className="flex items-center justify-end space-x-2 p-4">
                <Button variant="outline" size="sm" onClick={() => table.previousPage()} disabled={!table.getCanPreviousPage()}>
                    Previous
                </Button>
                <Button variant="outline" size="sm" onClick={() => table.nextPage()} disabled={!table.getCanNextPage()}>
                    Next
                </Button>
            </div>
        </div>
    );
}
