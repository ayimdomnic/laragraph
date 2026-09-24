<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

class ValidationTest extends GraphQLTestCase
{
    public function test_rules_run_before_the_resolver_and_report_per_field(): void
    {
        $errors = $this->graphql('mutation { createPost(input: { title: "Hi", body: "short" }) { id } }', as: $this->member)
            ->json('errors.0.extensions');

        $this->assertSame('validation', $errors['category']);
        // attributes() renames "input.title" to "title" in the messages.
        $this->assertSame(['The title field must be at least 3 characters.'], $errors['validation']['input.title']);
        $this->assertSame(['The body field must be at least 10 characters.'], $errors['validation']['input.body']);
    }

    public function test_rules_can_depend_on_each_other(): void
    {
        $this->graphql('{ organization { name } }')->assertJsonPath('errors.0.extensions.category', 'validation');
        $this->graphql('{ organization(slug: "acme") { name } }')->assertJsonPath('data.organization.name', 'Acme');
    }

    public function test_graphql_type_errors_are_reported_before_laravel_validation(): void
    {
        $this->graphql('{ search { __typename } }')
            ->assertJsonPath('errors.0.message', 'Field "search" argument "term" of type "String!" is required but not provided.');
    }

    public function test_argument_rules(): void
    {
        $this->graphql('{ search(term: "a") { __typename } }')
            ->assertJsonPath('errors.0.extensions.validation.term.0', 'The term field must be at least 2 characters.');

        $this->graphql('{ search(term: "acme", limit: 500) { __typename } }')
            ->assertJsonPath('errors.0.extensions.validation.limit.0', 'The limit field must be between 1 and 25.');
    }
}
