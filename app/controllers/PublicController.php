<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use Throwable;

final class PublicController
{
    public function home(): void
    {
        View::render('public/home', [
            'title' => 'Professional Exterior Cleaning',
            'services' => [
                ['icon' => 'fa-window-maximize', 'name' => 'Window Cleaning', 'description' => 'Streak-free residential and commercial glass cleaning.'],
                ['icon' => 'fa-house', 'name' => 'House Washing', 'description' => 'Low-pressure exterior cleaning for siding and trim.'],
                ['icon' => 'fa-water', 'name' => 'Pressure Washing', 'description' => 'Professional cleaning for patios, fences, and hard surfaces.'],
                ['icon' => 'fa-road', 'name' => 'Concrete Cleaning', 'description' => 'Restore driveways, sidewalks, and entryways.'],
                ['icon' => 'fa-house-flood-water', 'name' => 'Gutter Cleaning', 'description' => 'Remove debris and restore reliable water flow.'],
            ],
        ]);
    }

    public function services(): void { View::render('public/services', ['title' => 'Services']); }
    public function about(): void { View::render('public/about', ['title' => 'About']); }
    public function contact(): void { View::render('public/contact', ['title' => 'Contact']); }
    public function quote(): void { View::render('public/quote', ['title' => 'Request a Quote']); }

    public function submitQuote(): void
    {
        verify_csrf();
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $service = trim($_POST['service'] ?? '');
        $details = trim($_POST['details'] ?? '');
        if ($name === '' || $email === '' || $phone === '' || $service === '') {
            $_SESSION['flash_error'] = 'Please complete all required fields.';
            header('Location: /quote'); exit;
        }
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('INSERT INTO leads (name,email,phone,address,service_requested,details,status,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
            $stmt->execute([$name, $email, $phone, $address, $service, $details, 'new']);
            $_SESSION['flash_success'] = 'Your quote request has been received. We will contact you shortly.';
        } catch (Throwable) {
            $_SESSION['flash_error'] = 'We could not save the request. Please call us directly.';
        }
        header('Location: /quote'); exit;
    }
}
