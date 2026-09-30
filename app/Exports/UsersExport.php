<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromQuery, WithHeadings, WithMapping
{
    protected ?string $fromDate;
    protected ?string $toDate;
    protected ?string $userId;
    protected ?string $status;

    public function __construct(
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $userId = null,
        ?string $status = null
    ) {
        $this->fromDate = $fromDate;
        $this->toDate   = $toDate;
        $this->userId   = $userId;
        $this->status   = $status;
    }

    public function query(): Builder
    {
        return User::query()
            ->where('type', 'user')
            ->when(
                $this->userId !== null && $this->userId !== '',
                function (Builder $query) {
                    $query->where('id', $this->userId);
                }
            )
            ->when(
                $this->status !== null && $this->status !== '',
                function (Builder $query) {
                    $query->where('status', $this->status);
                }
            )
            ->when(
                $this->fromDate !== null && $this->fromDate !== '',
                function (Builder $query) {
                    $query->whereDate('created_at', '>=', $this->fromDate);
                }
            )
            ->when(
                $this->toDate !== null && $this->toDate !== '',
                function (Builder $query) {
                    $query->whereDate('created_at', '<=', $this->toDate);
                }
            )
            ->orderBy('id', 'asc');
    }

    public function headings(): array
    {
        return [
            'User ID',
            'Code',
            'Name',
            'Username',
            'Email',
            'Country Code',
            'Mobile',
            'Gender',
            'Date of Birth',
            'Birth Time',
            'Birth Place',
            'Address',
            'Pincode',
            'Marital Status',
            'Occupation',
            'Date of Joining',
            'Status',
            'Created At',
        ];
    }

    public function map($user): array
    {
        return [
            $user->id ?? '-',
            $user->code ?? '-',
            $user->name ?? '-',
            $user->username ?? '-',
            $user->email ?? '-',
            $this->formatCountryCode($user->country_code),
            $user->mobile ?? '-',
            $this->formatText($user->gender),
            $this->formatDate($user->dob),
            $this->formatTime($user->birth_time),
            $this->formatBirthPlace($user->birth_place),
            $user->address ?? '-',
            $user->pincode ?? '-',
            $this->formatText($user->marital_status),
            $user->occupation ?? '-',
            $this->formatDate($user->date_of_joining),
            $user->status == 1 ? 'Active' : 'Inactive',
            $this->formatDateTime($user->created_at),
        ];
    }

    private function formatCountryCode($countryCode): string
    {
        if ($countryCode === null || $countryCode === '') {
            return '-';
        }

        return '+' . ltrim(trim((string) $countryCode), '+');
    }

    private function formatDate($date): string
    {
        if (empty($date)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($date)->format('d-m-Y');
        } catch (\Throwable $e) {
            return '-';
        }
    }

    private function formatTime($time): string
    {
        if (empty($time)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($time)->format('h:i A');
        } catch (\Throwable $e) {
            return '-';
        }
    }

    private function formatDateTime($dateTime): string
    {
        if (empty($dateTime)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($dateTime)->format('d-m-Y h:i:s A');
        } catch (\Throwable $e) {
            return '-';
        }
    }

    private function formatText($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return ucwords(str_replace('_', ' ', (string) $value));
    }

    private function formatBirthPlace($birthPlace): string
    {
        if (empty($birthPlace)) {
            return '-';
        }

        if (is_array($birthPlace)) {
            return $birthPlace['displayName']
                ?? $birthPlace['place']
                ?? '-';
        }

        if (is_string($birthPlace)) {
            $decoded = json_decode($birthPlace, true);

            if (
                json_last_error() === JSON_ERROR_NONE &&
                is_array($decoded)
            ) {
                return $decoded['displayName']
                    ?? $decoded['place']
                    ?? '-';
            }

            return $birthPlace !== '' ? $birthPlace : '-';
        }

        return '-';
    }
}