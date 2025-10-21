<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251019_UserLeaveSettings extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Creates lie_user_leave_settings for employment types & time tracking";
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable("lie_user_leave_settings")) {
            return;
        }

        $table = $schema->createTable("lie_user_leave_settings");
        $table->addColumn("id", "integer", ["autoincrement" => true, "notnull" => true]);
        $table->addColumn("user_id", "integer", ["notnull" => true]);
        $table->addColumn("policy_id", "integer", ["notnull" => false]);
        $table->addColumn("employment_type", "string", ["length" => 20, "notnull" => true, "default" => "fulltime"]);
        $table->addColumn("contracted_hours_per_week", "float", ["notnull" => false]);
        $table->addColumn("working_time_percentage", "float", ["notnull" => true, "default" => 100.0]);
        $table->addColumn("use_kimai_time_tracking", "boolean", ["notnull" => true, "default" => false]);
        $table->addColumn("created_at", "datetime_immutable", ["notnull" => true]);
        $table->addColumn("updated_at", "datetime_immutable", ["notnull" => false]);
        
        $table->setPrimaryKey(["id"]);
        $table->addUniqueIndex(["user_id"], "uniq_lie_user_leave_settings_user");
        $table->addIndex(["policy_id"], "idx_lie_user_leave_settings_policy");
        $table->addIndex(["employment_type"], "idx_lie_user_leave_settings_type");
        $table->addIndex(["use_kimai_time_tracking"], "idx_lie_user_leave_settings_kimai_flag");
        
        $table->addForeignKeyConstraint("kimai2_users", ["user_id"], ["id"], ["onDelete" => "CASCADE"]);
        $table->addForeignKeyConstraint("lie_leave_policies", ["policy_id"], ["id"], ["onDelete" => "SET NULL"]);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable("lie_user_leave_settings")) {
            $schema->dropTable("lie_user_leave_settings");
        }
    }
}