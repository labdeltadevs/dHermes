<?php

namespace App\Enums;

enum Permission: string
{
    case UsersManage = 'users.manage';
    case AssignmentsManage = 'assignments.manage';
    case PortfolioView = 'portfolio.view';
    case ReceiptsView = 'receipts.view';
    case CollectionsView = 'collections.view';
    case CollectionsCreate = 'collections.create';
    case CollectionsApprove = 'collections.approve';
    case CollectionsCancelAny = 'collections.cancel.any';
    case CollectionsVoid = 'collections.void';
    case CashSessionsView = 'cash-sessions.view';
    case CashSessionsOperate = 'cash-sessions.operate';
    case CashSessionsApprove = 'cash-sessions.approve';
    case AuditView = 'audit.view';
    case VerificationCodesView = 'verification-codes.view';

    public function label(): string
    {
        return match ($this) {
            self::UsersManage => 'Gestionar usuarios',
            self::AssignmentsManage => 'Gestionar asignaciones',
            self::PortfolioView => 'Ver cartera',
            self::ReceiptsView => 'Ver cobros',
            self::CollectionsView => 'Ver cobranzas',
            self::CollectionsCreate => 'Registrar cobranzas',
            self::CollectionsApprove => 'Aprobar o rechazar cobranzas',
            self::CollectionsCancelAny => 'Anular cobranzas de cualquier usuario',
            self::CollectionsVoid => 'Anular cobranzas aprobadas',
            self::CashSessionsView => 'Ver arqueos',
            self::CashSessionsOperate => 'Operar arqueos propios',
            self::CashSessionsApprove => 'Aprobar o rechazar arqueos',
            self::AuditView => 'Ver auditoría',
            self::VerificationCodesView => 'Ver comprobantes verificables',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
