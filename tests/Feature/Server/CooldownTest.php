<?php

namespace Tests\Feature\Server;

use Expose\Server\Factory;
use React\Http\Browser;
use Tests\Feature\TestCase;

class CooldownTest extends TestCase
{
    /** @var Browser */
    protected $browser;

    /** @var Factory */
    protected $serverFactory;

    public function setUp(): void
    {
        parent::setUp();

        $this->browser = new Browser($this->loop);
        $this->browser = $this->browser->withFollowRedirects(false);

        $this->startServer();
    }

    public function tearDown(): void
    {
        $this->serverFactory->getSocket()->close();

        $this->await(\React\Promise\Timer\resolve(0.2, $this->loop));

        parent::tearDown();
    }

    /** @test */
    public function it_shows_cooldown_status_in_user_details()
    {
        // Create a user
        $authToken = 'cooldown-test-token';
        
        $this->await($this->browser->post('http://127.0.0.1:8080/api/users', [
            'Host' => 'expose.localhost',
            'Authorization' => base64_encode('username:secret'),
            'Content-Type' => 'application/json',
        ], json_encode([
            'name' => 'Cooldown Test User',
            'auth_token' => $authToken,
        ])));

        // Set cooldown for the user (simulate they were disconnected)
        $cooldownEndsAt = time() + (10 * 60); // 10 minutes from now
        $userRepo = app(\Expose\Server\Contracts\UserRepository::class);
        $this->await($userRepo->setCooldownForToken($authToken, $cooldownEndsAt));

        // Get user details
        $response = $this->await($this->browser->get('http://127.0.0.1:8080/api/users', [
            'Host' => 'expose.localhost',
            'Authorization' => base64_encode('username:secret'),
        ]));

        $body = json_decode($response->getBody()->getContents());
        $users = $body->paginated->users;
        
        $cooldownUser = null;
        foreach ($users as $user) {
            if ($user->auth_token === $authToken) {
                $cooldownUser = $user;
                break;
            }
        }

        $this->assertNotNull($cooldownUser);
        $this->assertTrue($cooldownUser->is_in_cooldown);
        $this->assertEquals(10, $cooldownUser->cooldown_minutes_remaining);
    }

    /** @test */
    public function it_can_configure_cooldown_period_via_admin_api()
    {
        // Get current settings
        $response = $this->await($this->browser->get('http://127.0.0.1:8080/api/settings', [
            'Host' => 'expose.localhost',
            'Authorization' => base64_encode('username:secret'),
        ]));

        $settings = json_decode($response->getBody()->getContents());
        
        // Update cooldown period
        $response = $this->await($this->browser->post('http://127.0.0.1:8080/api/settings', [
            'Host' => 'expose.localhost',
            'Authorization' => base64_encode('username:secret'),
            'Content-Type' => 'application/json',
        ], json_encode([
            'connection_cooldown_period' => 15,
            'validate_auth_tokens' => $settings->configuration->validate_auth_tokens,
            'maximum_connection_length' => $settings->configuration->maximum_connection_length,
            'messages' => [
                'invalid_auth_token' => $settings->configuration->messages->invalid_auth_token,
                'subdomain_taken' => $settings->configuration->messages->subdomain_taken,
                'message_of_the_day' => $settings->configuration->messages->message_of_the_day,
                'custom_subdomain_unauthorized' => $settings->configuration->messages->custom_subdomain_unauthorized,
                'connection_cooldown_active' => 'You must wait before reconnecting. Cooldown period expires in :minutes minutes.',
            ],
        ])));

        $updatedSettings = json_decode($response->getBody()->getContents());
        
        $this->assertEquals(15, $updatedSettings->configuration->connection_cooldown_period);
        $this->assertStringContainsString(':minutes', $updatedSettings->configuration->messages->connection_cooldown_active);
    }

    /** @test */
    public function it_shows_no_cooldown_for_users_without_cooldown()
    {
        // Create a user without cooldown
        $authToken = 'no-cooldown-user-token';
        
        $this->await($this->browser->post('http://127.0.0.1:8080/api/users', [
            'Host' => 'expose.localhost',
            'Authorization' => base64_encode('username:secret'),
            'Content-Type' => 'application/json',
        ], json_encode([
            'name' => 'Regular User',
            'auth_token' => $authToken,
        ])));

        // Get user details
        $response = $this->await($this->browser->get('http://127.0.0.1:8080/api/users', [
            'Host' => 'expose.localhost',
            'Authorization' => base64_encode('username:secret'),
        ]));

        $body = json_decode($response->getBody()->getContents());
        $users = $body->paginated->users;
        
        $regularUser = null;
        foreach ($users as $user) {
            if ($user->auth_token === $authToken) {
                $regularUser = $user;
                break;
            }
        }

        $this->assertNotNull($regularUser);
        $this->assertFalse($regularUser->is_in_cooldown);
        $this->assertEquals(0, $regularUser->cooldown_minutes_remaining);
    }

    protected function startServer()
    {
        $this->app['config']['expose-server.subdomain'] = 'expose';
        $this->app['config']['expose-server.database'] = ':memory:';

        $this->app['config']['expose-server.users'] = [
            'username' => 'secret',
        ];

        $this->serverFactory = new Factory();

        $this->serverFactory->setLoop($this->loop)
            ->createServer();
    }
}