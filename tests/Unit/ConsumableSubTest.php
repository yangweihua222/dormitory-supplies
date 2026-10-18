<?php

namespace Tests\Unit;

use App\Enums\ConsumableSub;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConsumableSubTest extends TestCase
{
    public function test_has_expected_cases(): void
    {
        $this->assertSame(
            ['appliance', 'bedding', 'cleaning', 'toiletries', 'prize', 'other'],
            array_column(ConsumableSub::cases(), 'value'),
        );
    }

    public function test_labels_are_unique_and_non_empty(): void
    {
        $labels = array_map(fn ($c) => $c->label(), ConsumableSub::cases());

        $this->assertSame($labels, array_unique($labels));
        $this->assertNotContains('', $labels);
    }

    public function test_other_label(): void
    {
        $this->assertSame('其他', ConsumableSub::OTHER->label());
    }

    public function test_options_format_for_frontend(): void
    {
        $options = ConsumableSub::options();

        $this->assertCount(6, $options);
        $this->assertContains(['value' => 'other', 'label' => '其他'], $options);
    }

    public static function caseLabelProvider(): array
    {
        return [
            '家电类' => [ConsumableSub::APPLIANCE, '家电类'],
            '床品类' => [ConsumableSub::BEDDING, '床品类'],
            '清洁类' => [ConsumableSub::CLEANING, '清洁类'],
            '洗化类' => [ConsumableSub::TOILETRIES, '洗化类'],
            '奖品类' => [ConsumableSub::PRIZE, '奖品类'],
            '其他' => [ConsumableSub::OTHER, '其他'],
        ];
    }

    #[DataProvider('caseLabelProvider')]
    public function test_labels(ConsumableSub $case, string $expected): void
    {
        $this->assertSame($expected, $case->label());
    }
}
