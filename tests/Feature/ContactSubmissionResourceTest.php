<?php

use App\Filament\Resources\ContactSubmissions\ContactSubmissionResource;
use App\Filament\Resources\ContactSubmissions\Pages\ManageContactSubmissions;
use App\Models\ContactSubmission;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

test('an administrator can reach the contact messages screen', function () {
    $this->actingAs($this->admin)
        ->get('/admin/contact-submissions')
        ->assertSuccessful();
});

test('an ordinary user cannot reach the contact messages screen', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/contact-submissions')
        ->assertForbidden();
});

test('a guest is sent to the login page', function () {
    $this->get('/admin/contact-submissions')->assertRedirect(route('login'));
});

test('the table lists the submissions', function () {
    $submissions = ContactSubmission::factory()->count(3)->create();

    $this->actingAs($this->admin);

    Livewire::test(ManageContactSubmissions::class)->assertCanSeeTableRecords($submissions);
});

test('an administrator can mark a submission handled and unhandled again', function () {
    $submission = ContactSubmission::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(ManageContactSubmissions::class)
        ->callTableAction('toggleHandled', $submission);

    expect($submission->refresh()->handled_at)->not->toBeNull();

    Livewire::test(ManageContactSubmissions::class)
        ->callTableAction('toggleHandled', $submission);

    expect($submission->refresh()->handled_at)->toBeNull();
});

test('an administrator can delete a submission', function () {
    $submission = ContactSubmission::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(ManageContactSubmissions::class)->callTableAction(DeleteAction::class, $submission);

    expect(ContactSubmission::count())->toBe(0);
});

/**
 * The badge is how an administrator learns a message arrived without opening the
 * screen, so a miscount is the whole feature failing quietly.
 */
test('the navigation badge counts only the unhandled submissions', function () {
    ContactSubmission::factory()->count(2)->create();
    ContactSubmission::factory()->handled()->count(3)->create();

    expect(ContactSubmissionResource::getNavigationBadge())->toBe('2');
});

test('the navigation badge is absent when nothing is waiting', function () {
    ContactSubmission::factory()->handled()->create();

    expect(ContactSubmissionResource::getNavigationBadge())->toBeNull();
});

/**
 * The rows are a record of what somebody actually sent. The resource offers no
 * create or edit form on purpose, and the policy denies `create` outright.
 */
test('there is no way to create a submission from the panel', function () {
    expect(ContactSubmissionResource::canCreate())->toBeFalse();
});

test('the message is shown as text rather than rendered', function () {
    $submission = ContactSubmission::factory()->create([
        'message' => 'Hello <script>alert("xss")</script> there',
    ]);

    $this->actingAs($this->admin);

    $content = Livewire::test(ManageContactSubmissions::class)
        ->callTableAction('view', $submission)
        ->html();

    expect($content)->not->toContain('<script>alert("xss")</script>');
});

test('the resource sits in the content navigation group', function () {
    expect(ContactSubmissionResource::getNavigationGroup())->toBe('Content');
});
