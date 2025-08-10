<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VoucherResource\Pages;
use App\Models\Voucher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Get; // Import Get untuk reactive fields

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket'; // Icon untuk voucher

    protected static ?string $navigationGroup = 'Manajemen Toko'; // Kelompokkan di navigasi admin

    protected static ?int $navigationSort = 3; // Urutan di dalam grup navigasi

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Detail Voucher')
                    ->description('Informasi dasar dan aturan penggunaan voucher.')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode Voucher')
                            ->required()
                            ->unique(ignoreRecord: true) // Pastikan kode unik, kecuali saat mengedit record yang sama
                            ->maxLength(255)
                            ->placeholder('Contoh: DISKON100K'),

                        Select::make('discount_type')
                            ->label('Tipe Diskon')
                            ->options([
                                'fixed' => 'Jumlah Tetap (Fixed Amount)',
                                'percent' => 'Persentase (Percentage)'
                            ])
                            ->required()
                            ->reactive(), // Membuat field ini reaktif untuk menampilkan/menyembunyikan field lain

                        TextInput::make('value')
                            ->label(fn(Get $get) => $get('discount_type') === 'percent' ? 'Nilai Diskon (%)' : 'Nilai Diskon (Rp)')
                            ->numeric()
                            ->required()
                            ->minValue(fn(Get $get) => $get('discount_type') === 'percent' ? 1 : 1000) // Min 1% atau Rp1000
                            ->maxValue(fn(Get $get) => $get('discount_type') === 'percent' ? 100 : null) // Max 100%
                            ->placeholder(fn(Get $get) => $get('discount_type') === 'percent' ? 'Contoh: 10 (untuk 10%)' : 'Contoh: 50000 (untuk Rp50.000)'),

                        DateTimePicker::make('expires_at')
                            ->label('Kadaluarsa Pada')
                            ->nullable()
                            ->minDate(now()) // Voucher tidak bisa kadaluarsa di masa lalu
                            ->placeholder('Opsional: Tanggal dan waktu kadaluarsa'),

                        TextInput::make('usage_limit')
                            ->label('Batas Penggunaan Total')
                            ->numeric()
                            ->nullable()
                            ->minValue(1)
                            ->placeholder('Opsional: Berapa kali voucher bisa digunakan (misal: 100)'),

                        // used_count tidak perlu di form create/edit, hanya untuk tampilan
                        // TextInput::make('used_count') 
                        //     ->label('Jumlah Digunakan')
                        //     ->numeric()
                        //     ->disabled(), // Tidak bisa diedit manual
                    ])->columns(2), // Atur layout kolom dalam section
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Voucher')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount_type')
                    ->label('Tipe Diskon')
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)) // Kapitalisasi huruf pertama
                    ->badge() // Tampilkan sebagai badge
                    ->color(fn(string $state): string => match ($state) {
                        'fixed' => 'info',
                        'percent' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('value')
                    ->label('Nilai')
                    ->formatStateUsing(function (string $state, Voucher $record): string {
                        if ($record->discount_type === 'percent') {
                            return "{$state}%";
                        }
                        return "Rp" . number_format((float)$state, 0, ',', '.');
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Kadaluarsa')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Tidak Ada Batas')
                    ->sortable(),

                Tables\Columns\TextColumn::make('usage_limit')
                    ->label('Batas Penggunaan')
                    ->placeholder('Tidak Terbatas')
                    ->sortable(),

                Tables\Columns\TextColumn::make('used_count')
                    ->label('Digunakan')
                    ->sortable()
                    ->default(0), // Pastikan default 0 jika null
            ])
            ->filters([
                // Filter berdasarkan tipe diskon
                Tables\Filters\SelectFilter::make('discount_type')
                    ->options([
                        'fixed' => 'Jumlah Tetap',
                        'percent' => 'Persentase',
                    ])
                    ->label('Tipe Diskon'),
                // Filter untuk voucher yang sudah kadaluarsa
                Tables\Filters\TernaryFilter::make('expires_at')
                    ->label('Kadaluarsa')
                    ->nullable()
                    ->trueLabel('Sudah Kadaluarsa')
                    ->falseLabel('Belum Kadaluarsa')
                    ->queries(
                        true: fn(Builder $query) => $query->whereNotNull('expires_at')->where('expires_at', '<', now()),
                        false: fn(Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()),
                        blank: fn(Builder $query) => $query,
                    ),
                // Filter untuk voucher yang sudah habis batas penggunaan
                Tables\Filters\TernaryFilter::make('usage_limit')
                    ->label('Batas Penggunaan Habis')
                    ->nullable()
                    ->trueLabel('Sudah Habis')
                    ->falseLabel('Belum Habis')
                    ->queries(
                        true: fn(Builder $query) => $query->whereNotNull('usage_limit')->whereColumn('used_count', '>=', 'usage_limit'),
                        false: fn(Builder $query) => $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'),
                        blank: fn(Builder $query) => $query,
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
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
            'index' => Pages\ListVouchers::route('/'),
            'create' => Pages\CreateVoucher::route('/create'),
            'edit' => Pages\EditVoucher::route('/{record}/edit'),
        ];
    }
}
