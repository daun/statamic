<?php

namespace Tests\Fieldtypes;

use Facades\Tests\Factories\EntryFactory;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades;
use Statamic\Fields\Field;
use Statamic\Fieldtypes\Entries;
use Tests\PreventSavingStacheItemsToDisk;
use Tests\TestCase;

class EntriesTaggableTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    public function setUp(): void
    {
        parent::setUp();

        Facades\Collection::make('blog')->save();
        EntryFactory::id('123')->collection('blog')->slug('one')->data(['title' => 'One'])->create();

        $this->actingAs(tap(Facades\User::make()->makeSuper())->save());
    }

    #[Test]
    public function it_keeps_existing_non_uuid_ids_untouched()
    {
        $processed = $this->fieldtype()->process(['123']);

        $this->assertEquals(['123'], $processed);
        $this->assertCount(1, Facades\Entry::all(), 'No new entry should have been created.');
    }

    #[Test]
    public function it_keeps_existing_uuid_ids_untouched()
    {
        $uuid = '3f2b1c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d';
        EntryFactory::id($uuid)->collection('blog')->slug('uuid-one')->data(['title' => 'Uuid One'])->create();

        $processed = $this->fieldtype()->process([$uuid]);

        $this->assertEquals([$uuid], $processed);
        $this->assertCount(2, Facades\Entry::all(), 'No new entry should have been created.');
    }

    #[Test]
    public function it_creates_an_entry_from_a_typed_string()
    {
        $processed = $this->fieldtype()->process(['Brand New Thing']);

        $this->assertCount(2, Facades\Entry::all());
        $created = Facades\Entry::query()->where('slug', 'brand-new-thing')->first();
        $this->assertNotNull($created);
        $this->assertEquals('Brand New Thing', $created->get('title'));
        $this->assertEquals([$created->id()], $processed);
    }

    #[Test]
    public function it_reuses_an_existing_entry_with_a_matching_slug()
    {
        $processed = $this->fieldtype()->process(['One']);

        $this->assertCount(1, Facades\Entry::all(), 'Should reuse the entry with slug "one".');
        $this->assertEquals(['123'], $processed);
    }

    #[Test]
    public function it_returns_a_single_id_when_max_items_is_one()
    {
        $processed = $this->fieldtype(['max_items' => 1])->process(['Another New Thing']);

        $created = Facades\Entry::query()->where('slug', 'another-new-thing')->first();
        $this->assertNotNull($created);
        $this->assertEquals($created->id(), $processed);
    }

    #[Test]
    public function it_does_not_create_entries_without_create_permission()
    {
        $this->actingAs(tap(Facades\User::make())->save());

        $processed = $this->fieldtype()->process(['Unauthorized Thing']);

        $this->assertCount(1, Facades\Entry::all(), 'Should not create an entry.');
        $this->assertEquals([], $processed);
    }

    #[Test]
    public function it_creates_the_entry_in_the_parents_site()
    {
        $parent = $this->setUpMultisite();

        $processed = $this->fieldtype([], $parent)->process(['Voitures']);

        $created = Facades\Entry::find($processed[0]);
        $this->assertEquals('fr', $created->locale(), 'Entry should be created in the parent entry site.');
    }

    #[Test]
    public function it_does_not_link_an_entry_from_a_different_site()
    {
        $parent = $this->setUpMultisite();

        EntryFactory::id('cars-en')->collection('tags')->locale('en')
            ->slug('cars')->data(['title' => 'Cars'])->create();

        $processed = $this->fieldtype([], $parent)->process(['Cars']);

        $this->assertNotEquals('cars-en', $processed[0], 'Should not link the English entry to a French parent.');
        $this->assertEquals('fr', Facades\Entry::find($processed[0])->locale());
    }

    #[Test]
    public function it_falls_back_to_a_collection_site_when_the_parent_site_is_unavailable()
    {
        $parent = $this->setUpMultisite();

        Facades\Collection::find('tags')->sites(['en'])->save();

        $processed = $this->fieldtype([], $parent)->process(['English Only']);

        $this->assertEquals('en', Facades\Entry::find($processed[0])->locale());
    }

    private function setUpMultisite()
    {
        $this->setSites([
            'en' => ['url' => 'http://localhost/', 'locale' => 'en'],
            'fr' => ['url' => 'http://localhost/fr/', 'locale' => 'fr'],
        ]);

        Facades\Collection::make('tags')->sites(['en', 'fr'])->save();
        Facades\Collection::make('articles')->sites(['en', 'fr'])->save();

        return EntryFactory::id('parent-fr')->collection('articles')->locale('fr')
            ->slug('parent')->data(['title' => 'Parent'])->create();
    }

    private function fieldtype($config = [], $parent = null)
    {
        $collection = $parent ? 'tags' : 'blog';

        $field = new Field('test', array_merge([
            'type' => 'entries',
            'collections' => [$collection],
        ], $config));

        if ($parent) {
            $field->setParent($parent);
        }

        return (new Entries)->setField($field);
    }
}
