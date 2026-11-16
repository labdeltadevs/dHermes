<?php

use App\Enums\TenantRole;
use App\Models\HeroSlide;
use App\Models\User;
use Livewire\Livewire;

it('lets only the platform administrator into the hero slides panel', function () {
    $tenant = tenantContext();
    $seller = memberFor($tenant, TenantRole::Seller);
    $tenantAdmin = memberFor($tenant, TenantRole::Admin);
    $platformAdmin = User::factory()->platformAdmin()->create();

    $this->actingAs($seller)
        ->get(route('admin.hero-slides'))
        ->assertForbidden();

    $this->actingAs($tenantAdmin)
        ->get(route('admin.hero-slides'))
        ->assertForbidden();

    $this->actingAs($platformAdmin)
        ->get(route('admin.hero-slides'))
        ->assertOk()
        ->assertSee('Comunicados');
});

it('creates, publishes, edits and deletes a hero slide from the panel', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();

    $this->actingAs($platformAdmin);

    Livewire::test('pages::admin.hero-slides')
        ->call('create')
        ->set('title', 'Mantenimiento programado')
        ->set('body', 'El sábado estaremos en mantenimiento.')
        ->set('sortOrder', 2)
        ->set('isPublished', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Mantenimiento programado');

    $slide = HeroSlide::query()->where('title', 'Mantenimiento programado')->firstOrFail();

    expect($slide->is_published)->toBeTrue()
        ->and($slide->sort_order)->toBe(2)
        ->and($slide->created_by)->toBe($platformAdmin->id);

    Livewire::test('pages::admin.hero-slides')
        ->call('togglePublished', $slide->id)
        ->assertHasNoErrors();

    expect($slide->fresh()?->is_published)->toBeFalse();

    Livewire::test('pages::admin.hero-slides')
        ->call('edit', $slide->id)
        ->assertSet('title', 'Mantenimiento programado')
        ->set('title', 'Mantenimiento reprogramado')
        ->call('save')
        ->assertHasNoErrors();

    expect($slide->fresh()?->title)->toBe('Mantenimiento reprogramado');

    Livewire::test('pages::admin.hero-slides')
        ->call('delete', $slide->id)
        ->assertHasNoErrors();

    expect(HeroSlide::query()->count())->toBe(0);
});

it('validates the title and message of a hero slide', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();

    $this->actingAs($platformAdmin);

    Livewire::test('pages::admin.hero-slides')
        ->call('create')
        ->set('title', '')
        ->set('body', '')
        ->call('save')
        ->assertHasErrors(['title', 'body']);

    expect(HeroSlide::query()->count())->toBe(0);
});

it('shows published hero slides on the dashboard and hides drafts', function () {
    $tenant = tenantContext();
    $seller = memberFor($tenant, TenantRole::Seller);

    HeroSlide::factory()->published()->create([
        'title' => 'Segundo comunicado',
        'body' => 'Se muestra después.',
        'sort_order' => 2,
    ]);

    HeroSlide::factory()->published()->create([
        'title' => 'Primer comunicado',
        'body' => 'Se muestra primero.',
        'sort_order' => 1,
    ]);

    HeroSlide::factory()->create([
        'title' => 'Borrador oculto',
        'body' => 'No debe aparecer.',
        'sort_order' => 0,
    ]);

    $this->actingAs($seller);

    Livewire::test('pages::dashboard')
        ->assertOk()
        ->assertSeeHtml('data-hero-slides')
        ->assertSeeHtmlInOrder(['Primer comunicado', 'Segundo comunicado'])
        ->assertDontSee('Borrador oculto');
});

it('does not render the hero slides popup when nothing is published', function () {
    $tenant = tenantContext();
    $seller = memberFor($tenant, TenantRole::Seller);

    HeroSlide::factory()->create([
        'title' => 'Solo borrador',
        'body' => 'No debe aparecer.',
    ]);

    $this->actingAs($seller);

    Livewire::test('pages::dashboard')
        ->assertOk()
        ->assertDontSeeHtml('data-hero-slides')
        ->assertDontSee('Solo borrador');
});
