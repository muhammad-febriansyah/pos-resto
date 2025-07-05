<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransaksiResource\Pages;
use App\Filament\Resources\TransaksiResource\RelationManagers;
use App\Models\Penjualan;
use App\Models\Setting;
use App\Models\Transaksi; // Meskipun Transaksi tidak digunakan langsung di sini, biarkan jika ada keperluan lain
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\TextEntry\TextEntrySize;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Http; // Import Http facade
use Filament\Notifications\Notification; // Import Notification
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon; // Import Carbon
use Illuminate\Support\Str;    // Import Str
use Illuminate\Support\Facades\Config; // Import Config for app name

class TransaksiResource extends Resource
{
    protected static ?string $model = Penjualan::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Penjualan';
    protected static ?string $navigationLabel = 'Transaksi';
    protected static ?int $navigationSort = 81;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jumlah Transaksi';
    }


    public static function getGlobalSearchResultTitle(Model $record): string | Htmlable
    {
        return $record->invoice_number;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice_number', 'user.name', 'customer.name'];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                TextColumn::make("No")->rowIndex(),
                TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable()
                    ->label('Nomor Invoice'),
                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->label('Kasir')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->label('Pelanggan')
                    ->default('Guest')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('meja.nama')
                    ->searchable()
                    ->sortable()
                    ->label('Meja')
                    ->default('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->label('Tipe Transaksi')
                    ->colors([
                        'primary' => 'dine_in',
                        'success' => 'take_away',
                        'warning' => 'delivery',
                    ])
                    ->sortable(),
                TextColumn::make('sub_total')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state))
                    ->sortable()
                    ->label('Sub Total'),
                TextColumn::make('ppn')
                    ->suffix('%')
                    ->label('PPN (%)')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('biaya_layanan')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state))
                    ->sortable()
                    ->label('Biaya Layanan')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state))
                    ->sortable()
                    ->label('Total Pembayaran')
                    ->summarize(Sum::make()->label('Total Keseluruhan')),
                TextColumn::make('laba')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state))
                    ->sortable()
                    ->label('Laba')
                    ->summarize(Sum::make()->label('Total Laba')),
                TextColumn::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->badge()
                    ->color(function (Penjualan $record) {
                        return match ($record->payment_method) {
                            'cash' => 'primary',
                            'midtrans' => 'success',
                            default => 'secondary',
                        };
                    })
                    ->sortable(),
                SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'challenge' => 'Challenge',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                    ])
                    ->sortable()
                    ->searchable(),

                TextColumn::make('created_at')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->label('Tanggal Transaksi'),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Tunai',
                        'midtrans' => 'Midtrans',
                    ])
                    ->label('Metode Pembayaran'),
                SelectFilter::make('type')
                    ->options([
                        'dine_in' => 'Dine In',
                        'take_away' => 'Take Away',
                        'delivery' => 'Delivery',
                    ])
                    ->label('Tipe Transaksi'),
                SelectFilter::make('status')
                    ->options([
                        'paid' => 'Lunas',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'challenge' => 'Challenge',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                    ])
                    ->label('Status Pembayaran'),
                SelectFilter::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Kasir'),
                SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Pelanggan'),
                SelectFilter::make('meja_id')
                    ->relationship('meja', 'nama')
                    ->searchable()
                    ->preload()
                    ->label('Meja'),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('Dari Tanggal'),
                        DatePicker::make('created_until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->label('Rentang Tanggal'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('confirm_cash_payment')
                    ->label('Konfirmasi Pembayaran Tunai')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Pembayaran Tunai?')
                    ->modalDescription('Apakah Anda yakin pembayaran tunai untuk invoice ini sudah diterima dan ingin menandainya sebagai LUNAS?')
                    ->modalSubmitActionLabel('Ya, Konfirmasi')
                    ->visible(fn(Penjualan $record): bool => $record->payment_method === 'cash' && $record->status === 'pending')
                    ->action(function (Penjualan $record) {
                        $record->status = 'paid';
                        $record->save();
                        $setting = Setting::first();
                        // Get app name from config
                        $appName = $setting->site_name; // 'Your App Name' is a fallback

                        // Generate WhatsApp message
                        $message = "🌟 *INVOICE PEMBELIAN - " . strtoupper($appName) . " 🌟*\n\n";
                        $message .= "Halo *" . ($record->customer->name ?? 'Pelanggan') . "*,\n";
                        $message .= "Terima kasih telah berbelanja di kami! Berikut rincian pesanan Anda:\n\n";

                        $message .= "```\n";
                        $message .= "--------------------------------------\n";
                        $message .= "🧾 INVOICE #" . $record->invoice_number . "\n";
                        $message .= "Tanggal: " . Carbon::parse($record->created_at)->translatedFormat('d M Y, H:i') . " WIB\n";
                        $message .= "Tipe: " . ucwords(str_replace('_', ' ', $record->type)) . "\n";
                        if ($record->type === 'dine_in' && $record->meja) {
                            $message .= "Meja: " . $record->meja->nama . "\n";
                        }
                        $message .= "--------------------------------------\n";
                        $message .= "🛒 Detail Pesanan:\n";
                        foreach ($record->details as $item) {
                            $message .= sprintf(
                                "%-20s %3dx Rp%-8s = Rp%s\n",
                                Str::limit($item->produk->nama_produk, 20, ''),
                                $item->qty,
                                number_format($item->produk->harga_jual, 0, ',', '.'),
                                number_format($item->subtotal_item, 0, ',', '.')
                            );
                        }
                        $message .= "--------------------------------------\n";
                        $message .= "💰 Ringkasan Pembayaran:\n";
                        $message .= sprintf("%-25s Rp%s\n", "Sub Total:", number_format($record->sub_total, 0, ',', '.'));
                        if ($record->ppn > 0) {
                            $message .= sprintf("%-25s Rp%s\n", "PPN (" . $record->ppn . "%):", number_format(($record->sub_total * $record->ppn / 100), 0, ',', '.'));
                        }
                        if ($record->biaya_layanan > 0) {
                            $message .= sprintf("%-25s Rp%s\n", "Biaya Layanan:", number_format($record->biaya_layanan, 0, ',', '.'));
                        }
                        $message .= "--------------------------------------\n";
                        $message .= sprintf("%-25s *Rp%s*\n", "Total Pembayaran:", number_format($record->total, 0, ',', '.'));
                        $message .= sprintf("%-25s %s\n", "Metode Pembayaran:", ucwords($record->payment_method));
                        $message .= "--------------------------------------\n";
                        $message .= "```\n\n";

                        $message .= "Pembayaran Anda telah *berhasil* diterima. Kami akan segera memproses pesanan Anda.\n";
                        $message .= "Terima kasih telah berbelanja di *" . $appName . "*! Kami menantikan kunjungan Anda kembali 😊\n\n";
                        $message .= "© " . Carbon::now()->year . " *" . $appName . "*";

                        // Send WhatsApp message
                        $customerPhone = $record->customer->phone;
                        if ($customerPhone) {
                            $waNumber = preg_replace('/[^0-9]/', '', $customerPhone);
                            // Ensure it starts with 62 if it's a valid Indonesian number format
                            if (substr($waNumber, 0, 1) === '0') {
                                $waNumber = '62' . substr($waNumber, 1);
                            } elseif (substr($waNumber, 0, 2) !== '62') {
                                // Assume it's an international number without +
                                $waNumber = '62' . $waNumber;
                            }

                            if (empty($waNumber) || strlen($waNumber) < 9) {
                                Log::warning("❌ WhatsApp message NOT sent: Invalid number for invoice " . $record->invoice_number . ". Input: {$customerPhone} | Cleaned: {$waNumber}");
                                Notification::make()
                                    ->title('Gagal Mengirim Notifikasi WhatsApp')
                                    ->body('Nomor telepon pelanggan tidak valid.')
                                    ->danger()
                                    ->send();
                            } else {
                                $waGatewayUrl = env('APP_WA_URL');
                                if (empty($waGatewayUrl)) {
                                    Log::warning("❌ WhatsApp message NOT sent for invoice " . $record->invoice_number . ": APP_WA_URL is not set in .env");
                                    Notification::make()
                                        ->title('Gagal Mengirim Notifikasi WhatsApp')
                                        ->body('URL Gateway WhatsApp tidak dikonfigurasi.')
                                        ->danger()
                                        ->send();
                                } else {
                                    try {
                                        Log::info("📤 Sending WhatsApp to {$waNumber} for invoice " . $record->invoice_number . " with message:\n" . $message);

                                        $curl = curl_init();
                                        curl_setopt_array($curl, [
                                            CURLOPT_URL => $waGatewayUrl,
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 30,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => [
                                                'message' => $message,
                                                'to' => $waNumber,
                                            ],
                                        ]);

                                        $response = curl_exec($curl);
                                        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                                        $err = curl_error($curl);
                                        curl_close($curl);

                                        if ($err) {
                                            Log::error("❌ Failed to send WA to {$waNumber} for invoice " . $record->invoice_number . ": cURL Error - {$err}");
                                            Notification::make()
                                                ->title('Gagal Mengirim Notifikasi WhatsApp')
                                                ->body('Terjadi kesalahan cURL saat mengirim WA.')
                                                ->danger()
                                                ->send();
                                        } elseif ($httpCode >= 400) {
                                            Log::error("❌ Failed to send WA to {$waNumber} for invoice " . $record->invoice_number . ": HTTP {$httpCode} - Response: {$response}");
                                            Notification::make()
                                                ->title('Gagal Mengirim Notifikasi WhatsApp')
                                                ->body("Server WA Gateway merespons dengan kesalahan HTTP {$httpCode}.")
                                                ->danger()
                                                ->send();
                                        } else {
                                            Log::info("✅ WhatsApp sent to {$waNumber} for invoice " . $record->invoice_number . " | Response: {$response}");
                                            Notification::make()
                                                ->title('Pembayaran Dikonfirmasi!')
                                                ->body('Status transaksi berhasil diubah menjadi Lunas dan notifikasi WhatsApp telah dikirim.')
                                                ->success()
                                                ->send();
                                        }
                                    } catch (\Exception $e) {
                                        Log::error("❌ Exception while sending WhatsApp to {$waNumber} for invoice " . $record->invoice_number . ": " . $e->getMessage(), [
                                            'trace' => $e->getTraceAsString(),
                                        ]);
                                        Notification::make()
                                            ->title('Gagal Mengirim Notifikasi WhatsApp')
                                            ->body('Terjadi kesalahan tak terduga saat mengirim WA.')
                                            ->danger()
                                            ->send();
                                    }
                                }
                            }
                        } else {
                            Notification::make()
                                ->title('Pembayaran Dikonfirmasi!')
                                ->body('Status transaksi berhasil diubah menjadi Lunas. Tidak ada nomor WhatsApp untuk dikirim.')
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Grid::make(3)
                    ->schema([
                        Group::make()
                            ->columnSpan(2)
                            ->schema([
                                Section::make('Detail Penjualan')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('invoice_number')
                                                    ->label('Nomor Invoice')
                                                    ->copyable()
                                                    ->copyMessage('Nomor invoice disalin!')
                                                    ->copyMessageDuration(1500),
                                                TextEntry::make('created_at')
                                                    ->label('Tanggal Transaksi')
                                                    ->dateTime('d M Y, H:i:s'),
                                                TextEntry::make('user.name')
                                                    ->label('Kasir'),
                                                TextEntry::make('status')
                                                    ->label('Status Pembayaran')
                                                    ->badge()
                                                    ->color(fn(string $state): string => match ($state) {
                                                        'paid' => 'success',
                                                        'pending' => 'warning',
                                                        'cancelled' => 'danger',
                                                        'challenge' => 'info',
                                                        'expired' => 'danger',
                                                        'failed' => 'danger',
                                                        default => 'gray',
                                                    }),
                                                TextEntry::make('payment_method')
                                                    ->label('Metode Pembayaran')
                                                    ->badge()
                                                    ->color(fn(string $state): string => match ($state) {
                                                        'cash' => 'success',
                                                        'midtrans' => 'info',
                                                        default => 'gray',
                                                    }),
                                                TextEntry::make('type')
                                                    ->label('Tipe Transaksi')
                                                    ->badge()
                                                    ->color(fn(string $state): string => match ($state) {
                                                        'dine_in' => 'primary',
                                                        'take_away' => 'success',
                                                        'delivery' => 'warning',
                                                        default => 'gray',
                                                    }),
                                            ]),
                                    ]),

                                Section::make('Produk Dibeli')
                                    ->schema([
                                        RepeatableEntry::make('details')
                                            ->label('')
                                            ->schema([
                                                TextEntry::make('produk.nama_produk')
                                                    ->label('Produk')
                                                    ->columnSpan(2),
                                                TextEntry::make('qty')
                                                    ->label('Qty')
                                                    ->numeric(),
                                                TextEntry::make('produk.harga_jual')
                                                    ->label('Harga')
                                                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state)),
                                                TextEntry::make('subtotal_item')
                                                    ->label('Subtotal')
                                                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state)),

                                            ])
                                            ->columns(5)
                                            ->columnSpanFull()
                                            ->grid(1),
                                    ]),

                            ]),

                        Group::make()
                            ->columnSpan(1)
                            ->schema([
                                Section::make('Info Pelanggan & Meja')
                                    ->schema([
                                        TextEntry::make('customer.name')
                                            ->label('Nama Pelanggan')
                                            ->default('Guest'),
                                        TextEntry::make('customer.email')
                                            ->label('Email Pelanggan')
                                            ->default('-'),
                                        TextEntry::make('customer.phone')
                                            ->label('Telepon Pelanggan')
                                            ->default('-'),
                                        TextEntry::make('meja.nama')
                                            ->label('Nama Meja')
                                            ->default('Tanpa Meja'),
                                        TextEntry::make('meja.status')
                                            ->label('Status Meja')
                                            ->default('-')
                                            ->badge()
                                            ->color(fn(string $state): string => match ($state) {
                                                'tersedia' => 'success',
                                                'dipakai' => 'danger',
                                                default => 'gray',
                                            })
                                            ->hidden(fn(?string $state) => !$state || $state === '-'),
                                    ]),

                                Section::make('Ringkasan Pembayaran')
                                    ->schema([
                                        TextEntry::make('sub_total')
                                            ->label('Sub Total')
                                            ->formatStateUsing(fn($state) => 'Rp ' . number_format($state)),
                                        TextEntry::make('ppn')
                                            ->label('PPN')
                                            ->formatStateUsing(fn($state) => $state . '%'),
                                        TextEntry::make('biaya_layanan')
                                            ->label('Biaya Layanan')
                                            ->formatStateUsing(fn($state) => 'Rp ' . number_format($state)),
                                        TextEntry::make('total')
                                            ->label('Total Pembayaran')
                                            ->formatStateUsing(fn($state) => 'Rp ' . number_format($state))
                                            ->size(TextEntrySize::Large)
                                            ->color('primary')
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('laba')
                                            ->label('Total Laba')
                                            ->formatStateUsing(fn($state) => 'Rp ' . number_format($state))
                                            ->size(TextEntrySize::Medium)
                                            ->color('success')
                                            ->weight(FontWeight::Bold),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransaksis::route('/'),
            'create' => Pages\CreateTransaksi::route('/create'),
            'edit' => Pages\EditTransaksi::route('/{record}/edit'),
        ];
    }
}
