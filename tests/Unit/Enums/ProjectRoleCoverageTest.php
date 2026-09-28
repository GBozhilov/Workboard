<?php

namespace Tests\Unit\Enums;

use App\Enums\ProjectRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ValueError;

class ProjectRoleCoverageTest extends TestCase
{
    public function test_project_role_has_two_cases(): void
    {
        $this->assertCount(2, ProjectRole::cases());
    }

    /**
     * @return array<string, array{0: ProjectRole}>
     */
    public static function roleCaseProvider(): array
    {
        return [
            'owner' => [ProjectRole::Owner],
            'member' => [ProjectRole::Member],
        ];
    }

    /**
     * @return array<string, array{0: ProjectRole, 1: string}>
     */
    public static function roleValueProvider(): array
    {
        return [
            'owner' => [ProjectRole::Owner, 'owner'],
            'member' => [ProjectRole::Member, 'member'],
        ];
    }

    #[DataProvider('roleValueProvider')]
    public function test_project_role_backing_values(ProjectRole $role, string $value): void
    {
        $this->assertSame($value, $role->value);
    }

    #[DataProvider('roleValueProvider')]
    public function test_project_role_from_accepts_value(ProjectRole $role, string $value): void
    {
        $this->assertSame($role, ProjectRole::from($value));
    }

    #[DataProvider('roleValueProvider')]
    public function test_project_role_try_from_accepts_value(ProjectRole $role, string $value): void
    {
        $this->assertSame($role, ProjectRole::tryFrom($value));
    }

    #[DataProvider('roleCaseProvider')]
    public function test_project_role_labels_are_non_empty(ProjectRole $role): void
    {
        $this->assertNotSame('', $role->label());
    }

    public function test_project_role_owner_label(): void
    {
        $this->assertSame('Owner', ProjectRole::Owner->label());
    }

    public function test_project_role_member_label(): void
    {
        $this->assertSame('Member', ProjectRole::Member->label());
    }

    public function test_project_role_try_from_rejects_invalid_value(): void
    {
        $this->assertNull(ProjectRole::tryFrom('admin'));
    }

    public function test_project_role_from_throws_on_invalid_value(): void
    {
        $this->expectException(ValueError::class);

        ProjectRole::from('invalid');
    }
}
