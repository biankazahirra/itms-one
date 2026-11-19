<?php

use App\Models\ActivityLog;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Admin ITMS']);
});

function historyEntry(string $entityType, string $action, string $description, string $at): ActivityLog
{
    return ActivityLog::create([
        'user_id' => test()->user->id,
        'entity_type' => $entityType,
        'entity_id' => 1,
        'action' => $action,
        'description' => $description,
        'created_at' => $at,
    ]);
}

it('redirects guests from the activity history page', function () {
    $this->get(route('activity.index'))->assertRedirect(route('login'));
});

it('lists activity newest first with actor, module, action, and summary', function () {
    historyEntry('purchase_request', 'created', 'Membuat PR pertama.', '2026-11-15 08:00:00');
    historyEntry('lhstp', 'finalized', 'Finalisasi LHSTP kedua.', '2026-11-16 09:00:00');

    $response = $this->actingAs($this->user)->get(route('activity.index'));

    $response->assertOk()
        ->assertSee('Finalisasi LHSTP kedua.')
        ->assertSee('Membuat PR pertama.')
        ->assertSee('Admin ITMS')
        ->assertSee('LHSTP')
        ->assertSee('Difinalisasi');

    expect(strpos($response->getContent(), 'Finalisasi LHSTP kedua.'))
        ->toBeLessThan(strpos($response->getContent(), 'Membuat PR pertama.'));
});

it('filters activity by module, action, and date range', function () {
    historyEntry('purchase_request', 'created', 'Membuat PR lama.', '2026-10-01 08:00:00');
    historyEntry('purchase_request', 'updated', 'Memperbarui PR baru.', '2026-11-16 09:00:00');
    historyEntry('lhstp', 'finalized', 'Finalisasi LHSTP.', '2026-11-17 09:00:00');

    $this->actingAs($this->user)
        ->get(route('activity.index', ['module' => 'purchase_request', 'action' => 'updated']))
        ->assertOk()
        ->assertSee('Memperbarui PR baru.')
        ->assertDontSee('Membuat PR lama.')
        ->assertDontSee('Finalisasi LHSTP.');

    $this->actingAs($this->user)
        ->get(route('activity.index', ['date_from' => '2026-11-01', 'date_to' => '2026-11-16']))
        ->assertOk()
        ->assertSee('Memperbarui PR baru.')
        ->assertDontSee('Membuat PR lama.')
        ->assertDontSee('Finalisasi LHSTP.');
});

it('shows an empty state when no activity matches the filters', function () {
    $this->actingAs($this->user)
        ->get(route('activity.index', ['module' => 'budget']))
        ->assertOk()
        ->assertSee('Belum ada aktivitas');
});
