<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * LieTimeOffBundle: Initial setup for leave management system
 */
final class Version20251018090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Creates tables for LieTimeOffBundle leave management system";
    }

    public function up(Schema $schema): void
    {
        // LeavePolicy
        $t = $schema->createTable("lie_leave_policy");
        $t->addColumn("id", "integer", ["autoincrement" => true, "notnull" => true]);
        $t->addColumn("name", "string", ["length" => 100, "notnull" => true]);
        $t->addColumn("description", "text", ["notnull" => false]);
        $t->addColumn("annual_days", "decimal", ["precision" => 5, "scale" => 2, "notnull" => true, "default" => "25.00"]);
        $t->addColumn("max_carryover", "decimal", ["precision" => 5, "scale" => 2, "notnull" => true, "default" => "5.00"]);
        $t->addColumn("is_default", "boolean", ["notnull" => true, "default" => false]);
        $t->addColumn("is_active", "boolean", ["notnull" => true, "default" => true]);
        $t->addColumn("created_at", "datetime", ["notnull" => true]);
        $t->addColumn("updated_at", "datetime", ["notnull" => true]);
        $t->setPrimaryKey(["id"]);

        // LeaveRequest
        $t = $schema->createTable("lie_leave_request");
        $t->addColumn("id", "integer", ["autoincrement" => true, "notnull" => true]);
        $t->addColumn("user_id", "integer", ["notnull" => true]);
        $t->addColumn("type", "string", ["length" => 20, "notnull" => true]);
        $t->addColumn("start_date", "date", ["notnull" => true]);
        $t->addColumn("end_date", "date", ["notnull" => true]);
        $t->addColumn("days", "decimal", ["precision" => 5, "scale" => 2, "notnull" => true]);
        $t->addColumn("status", "string", ["length" => 20, "notnull" => true, "default" => "pending"]);
        $t->addColumn("comment", "text", ["notnull" => false]);
        $t->addColumn("rejection_reason", "text", ["notnull" => false]);
        $t->addColumn("approved_by_id", "integer", ["notnull" => false]);
        $t->addColumn("approved_at", "datetime", ["notnull" => false]);
        $t->addColumn("created_at", "datetime", ["notnull" => true]);
        $t->addColumn("updated_at", "datetime", ["notnull" => true]);
        $t->setPrimaryKey(["id"]);
        $t->addIndex(["user_id"], "IDX_LTO_REQUEST_USER");
        $t->addIndex(["status"], "IDX_LTO_REQUEST_STATUS");
        $t->addForeignKeyConstraint("kimai2_users", ["user_id"], ["id"], ["onDelete" => "CASCADE"], "FK_LTO_REQUEST_USER");
        $t->addForeignKeyConstraint("kimai2_users", ["approved_by_id"], ["id"], ["onDelete" => "SET NULL"], "FK_LTO_REQUEST_APPROVER");

        // LeaveBalance
        $t = $schema->createTable("lie_leave_balance");
        $t->addColumn("id", "integer", ["autoincrement" => true, "notnull" => true]);
        $t->addColumn("user_id", "integer", ["notnull" => true]);
        $t->addColumn("policy_id", "integer", ["notnull" => true]);
        $t->addColumn("year", "integer", ["notnull" => true]);
        $t->addColumn("annual_entitlement", "decimal", ["precision" => 5, "scale" => 2, "notnull" => true]);
        $t->addColumn("carryover_from_previous_year", "decimal", ["precision" => 5, "scale" => 2, "notnull" => true, "default" => "0.00"]);
        $t->addColumn("taken", "decimal", ["precision" => 5, "scale" => 2, "notnull" => true, "default" => "0.00"]);
        $t->addColumn("approved", "decimal", ["precision" => 5, "scale" => 2, "notnull" => true, "default" => "0.00"]);
        $t->addColumn("manual_adjustment", "decimal", ["precision" => 6, "scale" => 2, "notnull" => true, "default" => "0.00"]);
        $t->addColumn("adjustment_note", "text", ["notnull" => false]);
        $t->addColumn("created_at", "datetime", ["notnull" => true]);
        $t->addColumn("updated_at", "datetime", ["notnull" => true]);
        $t->setPrimaryKey(["id"]);
        $t->addUniqueIndex(["user_id", "year"], "UNQ_LTO_BALANCE_USER_YEAR");
        $t->addIndex(["user_id"], "IDX_LTO_BALANCE_USER");
        $t->addIndex(["year"], "IDX_LTO_BALANCE_YEAR");
        $t->addForeignKeyConstraint("kimai2_users", ["user_id"], ["id"], ["onDelete" => "CASCADE"], "FK_LTO_BALANCE_USER");
        $t->addForeignKeyConstraint("lie_leave_policy", ["policy_id"], ["id"], [], "FK_LTO_BALANCE_POLICY");

        // Holiday
        $t = $schema->createTable("lie_holiday");
        $t->addColumn("id", "integer", ["autoincrement" => true, "notnull" => true]);
        $t->addColumn("date", "date", ["notnull" => true]);
        $t->addColumn("name", "string", ["length" => 100, "notnull" => true]);
        $t->addColumn("type", "string", ["length" => 50, "notnull" => true, "default" => "public"]);
        $t->addColumn("is_active", "boolean", ["notnull" => true, "default" => true]);
        $t->addColumn("created_at", "datetime", ["notnull" => true]);
        $t->setPrimaryKey(["id"]);
        $t->addUniqueIndex(["date"], "UNQ_LTO_HOLIDAY_DATE");
        $t->addIndex(["date"], "IDX_LTO_HOLIDAY_DATE");
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable("lie_leave_balance");
        $schema->dropTable("lie_leave_request");
        $schema->dropTable("lie_holiday");
        $schema->dropTable("lie_leave_policy");
    }
}