<?php

namespace Tests\Feature;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Pages\ManageDocuments;
use App\Models\Campus;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);
        $this->travelTo('2026-11-11 10:00');
    }

    private function user(string $role, ?Campus $branch = null): User
    {
        $user = User::factory()->create(['campus_id' => $branch?->id]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return list<int>
     */
    private function visibleTo(User $user): array
    {
        return Document::query()->availableTo($user)->orderBy('id')->pluck('id')->all();
    }

    public function test_staff_see_the_documents_shared_with_their_role_while_they_are_visible(): void
    {
        $forSecretaries = Document::factory()->sharedWith(['secretary'])->create();
        $forTeachers = Document::factory()->sharedWith([Document::TEACHERS])->create();
        $scheduled = Document::factory()->sharedWith(['secretary'])->create(['visible_from' => '2026-11-20']);
        $expired = Document::factory()->sharedWith(['secretary'])->create(['visible_until' => '2026-11-10']);
        $lastDay = Document::factory()->sharedWith(['secretary', 'viewer'])->create(['visible_until' => '2026-11-11']);

        $viewer = $this->user('viewer');
        $teacher = Teacher::factory()->create()->user;
        $admin = $this->user('admin');

        $this->assertSame([$lastDay->id], $this->visibleTo($viewer));
        $this->assertSame([$forTeachers->id], $this->visibleTo($teacher));
        $this->assertSame([$forSecretaries->id, $forTeachers->id, $scheduled->id, $expired->id, $lastDay->id], $this->visibleTo($admin));
        $this->assertSame(['visible', 'scheduled', 'expired', 'visible'], [$forSecretaries->visibility(), $scheduled->visibility(), $expired->visibility(), $lastDay->visibility()]);
    }

    public function test_documents_of_a_branch_are_only_seen_at_that_branch(): void
    {
        $branch = Campus::factory()->create();
        $otherBranch = Campus::factory()->create();
        $everywhere = Document::factory()->sharedWith(['viewer'])->create();
        $here = Document::factory()->sharedWith(['viewer'])->create(['campus_id' => $branch->id]);
        $there = Document::factory()->sharedWith(['viewer'])->create(['campus_id' => $otherBranch->id]);
        $teacher = Teacher::factory()->create();
        $teacher->campuses()->attach($branch);
        Document::query()->each(fn (Document $document) => $document->shareWith(['viewer', Document::TEACHERS]));

        $this->assertSame([$everywhere->id, $here->id], $this->visibleTo($this->user('viewer', $branch)));
        $this->assertSame([$everywhere->id, $here->id, $there->id], $this->visibleTo($this->user('viewer')));
        $this->assertSame([$everywhere->id, $here->id], $this->visibleTo($teacher->user));
    }

    public function test_staff_upload_a_document_and_share_it(): void
    {
        Storage::fake('local');
        $user = $this->user('secretary');
        $this->actingAs($user);
        $folder = DocumentFolder::factory()->create(['name' => 'Policies']);

        Livewire::test(ManageDocuments::class)
            ->callAction('create', data: [
                'document_folder_id' => $folder->id,
                'title' => 'Leave policy 2026',
                'file' => UploadedFile::fake()->create('leave-policy.pdf', 120, 'application/pdf'),
                'audiences' => ['secretary', Document::TEACHERS],
                'visible_until' => '2027-03-31',
            ])
            ->assertHasNoActionErrors();

        $document = Document::query()->sole();
        $this->assertSame('Leave policy 2026', $document->title);
        $this->assertSame($user->id, $document->uploaded_by);
        $this->assertEqualsCanonicalizing(['secretary', Document::TEACHERS], $document->audiences()->pluck('audience')->all());
        $this->assertSame('leave-policy.pdf', $document->getFirstMedia('file')->file_name);
        $this->assertSame('local', $document->getFirstMedia('file')->disk);
    }

    public function test_changing_the_sharing_removes_the_audiences_left_out(): void
    {
        $document = Document::factory()->sharedWith(['secretary', 'viewer'])->create();

        $document->shareWith([Document::TEACHERS]);

        $this->assertSame([Document::TEACHERS], $document->audiences()->pluck('audience')->all());
    }

    public function test_staff_download_the_documents_shared_with_them(): void
    {
        Storage::fake('local');
        $document = Document::factory()->sharedWith(['viewer'])->create();
        $document->addMedia(UploadedFile::fake()->create('circular.pdf', 10, 'application/pdf'))->toMediaCollection('file');
        $this->actingAs($this->user('viewer'));

        Livewire::test(ManageDocuments::class)
            ->assertCanSeeTableRecords([$document])
            ->assertActionHidden('create')
            ->assertActionHidden(TestAction::make('edit')->table($document))
            ->callAction(TestAction::make('download')->table($document))
            ->assertFileDownloaded('circular.pdf');
    }

    public function test_every_member_of_staff_opens_the_documents(): void
    {
        $this->actingAs(Teacher::factory()->create()->user);

        $this->assertTrue(DocumentResource::canAccess());
        $this->get(DocumentResource::getUrl())->assertSuccessful();
    }
}
