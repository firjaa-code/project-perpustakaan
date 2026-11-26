<?php

namespace App\Exports;

use App\Models\Pinjam;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PinjamExport implements FromCollection, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        return Pinjam::with(['pinjam_detail.buku', 'pinjam_detail.petugas_kembali', 'petugas_pinjam'])
            ->whereHas('pinjam_detail', function ($query) {
                $query->where('status', 'Pinjam');
            })
            ->whereBetween('tgl_pinjam', [$this->startDate, $this->endDate])
            ->orderBy('no_pinjam', 'DESC')
            ->get();
    }

    public function map($row): array
    {
        $mappedRows = [];
        foreach ($row->pinjam_detail as $detail) {
            $mappedRows[] = [
                $row->no_pinjam,
                \Carbon\Carbon::parse($row->tgl_pinjam)->format('d-m-Y'),
                $detail->buku->judul_buku ?? 'Tidak Ada Judul',
                $detail->status,
                $row->petugas_pinjam->nama ?? 'Tidak Ada Petugas',
            ];
        }
        return $mappedRows;
    }

    public function headings(): array
    {
        return [
            'No Peminjaman',
            'Tanggal Pinjam',
            'Judul Buku',
            'Status',
            'Petugas Pinjam',
        ];
    }
}