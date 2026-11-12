<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state: a document of all branches, always visible, shared with nobody yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_folder_id' => DocumentFolder::factory(),
            'title' => fake()->sentence(3),
        ];
    }

    /**
     * @param  list<string>  $audiences
     */
    public function sharedWith(array $audiences): static
    {
        return $this->afterCreating(fn (Document $document) => $document->shareWith($audiences));
    }
}
