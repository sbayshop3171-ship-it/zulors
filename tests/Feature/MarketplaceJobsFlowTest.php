<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\JobListing;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Enums\Category\CategoryType;
use App\Enums\Category\CategoryStatus;
use App\Enums\Product\ProductStatus;
use App\Enums\Product\ProductApproval;
use App\Enums\Job\JobStatus;
use App\Enums\Job\JobApproval;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Notifications\User\Job\JobApprovedNotification;
use App\Notifications\User\Job\JobRejectedNotification;

class MarketplaceJobsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_approving_a_job_sends_a_notification_to_the_owner(): void
    {
        Notification::fake();

        $admin = $this->createUser(role: UserRole::ADMIN);
        $owner = $this->createUser(username: 'job-owner', email: 'job-owner@example.com');
        $jobCategory = $this->createCategory(CategoryType::JOB, 'Design jobs');
        $job = $this->createJob($owner, $jobCategory, approval: JobApproval::PENDING);

        $this->actingAs($admin)
            ->withoutMiddleware()
            ->from(route('admin.jobs.show', $job->id))
            ->post(route('admin.jobs.approve', $job->id))
            ->assertRedirect(route('admin.jobs.show', $job->id));

        $this->assertDatabaseHas('job_listings', [
            'id' => $job->id,
            'approval' => JobApproval::APPROVED->value,
        ]);

        Notification::assertSentTo($owner, JobApprovedNotification::class);
    }

    public function test_admin_rejecting_a_job_sends_a_notification_to_the_owner(): void
    {
        Notification::fake();

        $admin = $this->createUser(role: UserRole::ADMIN, username: 'admin-reject', email: 'admin-reject@example.com');
        $owner = $this->createUser(username: 'job-owner-2', email: 'job-owner-2@example.com');
        $jobCategory = $this->createCategory(CategoryType::JOB, 'Remote jobs');
        $job = $this->createJob($owner, $jobCategory, approval: JobApproval::PENDING);

        $this->actingAs($admin)
            ->withoutMiddleware()
            ->from(route('admin.jobs.show', $job->id))
            ->post(route('admin.jobs.reject', $job->id))
            ->assertRedirect(route('admin.jobs.show', $job->id));

        $this->assertDatabaseHas('job_listings', [
            'id' => $job->id,
            'approval' => JobApproval::REJECTED->value,
        ]);

        Notification::assertSentTo($owner, JobRejectedNotification::class);
    }

    public function test_business_user_can_delete_a_job_and_receive_feedback(): void
    {
        $owner = $this->createUser(username: 'business-job-owner', email: 'business-job-owner@example.com');
        $jobCategory = $this->createCategory(CategoryType::JOB, 'Ops jobs');
        $job = $this->createJob($owner, $jobCategory);

        $this->actingAs($owner)
            ->withoutMiddleware()
            ->post(route('business.jobs.destroy', $job->id))
            ->assertRedirect(route('business.jobs.index'));

        $this->assertDatabaseMissing('job_listings', [
            'id' => $job->id,
        ]);
    }

    public function test_business_user_can_delete_a_product_and_receive_feedback(): void
    {
        $owner = $this->createUser(username: 'business-product-owner', email: 'business-product-owner@example.com');
        $productCategory = $this->createCategory(CategoryType::PRODUCT, 'Catalog items');
        $product = $this->createProduct($owner, $productCategory);

        $this->actingAs($owner)
            ->withoutMiddleware()
            ->post(route('business.market.destroy', $product->id))
            ->assertRedirect(route('business.market.index'));

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    private function createUser(
        UserRole $role = UserRole::USER,
        string $username = 'test-user',
        string $email = 'test-user@example.com',
        UserType $type = UserType::AUTHOR,
        UserStatus $status = UserStatus::ACTIVE
    ): User {
        return User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => $username,
            'caption' => '@' . $username,
            'email' => $email,
            'phone' => '',
            'website' => '',
            'bio' => '',
            'country' => null,
            'city' => null,
            'birth_day' => null,
            'birth_month' => null,
            'birth_year' => null,
            'age' => null,
            'gender' => 'male',
            'last_active' => now()->timestamp,
            'language' => 'en',
            'avatar' => null,
            'cover' => null,
            'verified' => false,
            'tips' => [],
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => $role,
            'theme' => 'light',
            'publications_count' => 0,
            'followers_count' => 0,
            'following_count' => 0,
            'status' => $status,
            'type' => $type,
        ]);
    }

    private function createCategory(CategoryType $type, string $name): Category
    {
        return Category::query()->create([
            'localization' => ['en' => $name],
            'parent_id' => null,
            'categorizable_type' => $type,
            'depth' => 1,
            'status' => CategoryStatus::ACTIVE,
        ]);
    }

    private function createProduct(User $owner, Category $category, ProductApproval $approval = ProductApproval::APPROVED): Product
    {
        return Product::query()->create([
            'user_id' => $owner->id,
            'title' => 'Test product',
            'category_id' => $category->id,
            'description' => 'Product description',
            'stock_quantity' => 10,
            'status' => ProductStatus::ACTIVE,
            'price' => 111,
            'discount' => 0,
            'address' => 'Test address',
            'views_count' => 0,
            'contacts_count' => 0,
            'bookmarks_count' => 0,
            'last_contacted_at' => now(),
            'currency' => 'USD',
            'approval' => $approval,
            'type' => 'physical',
            'condition' => 'new',
        ]);
    }

    private function createJob(User $owner, Category $category, JobApproval $approval = JobApproval::APPROVED): JobListing
    {
        return JobListing::query()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Test job',
            'description' => 'Job description',
            'overview' => 'Job overview',
            'status' => JobStatus::ACTIVE,
            'views_count' => 0,
            'applications_count' => 0,
            'bookmarks_count' => 0,
            'income' => 121,
            'is_start_income' => true,
            'currency' => 'USD',
            'approval' => $approval,
            'location' => 'Test location',
            'is_remote' => false,
            'is_urgent' => false,
            'type' => 'vacancy',
            'last_contacted_at' => now(),
        ]);
    }
}
