<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<main class="container main-content">
    <section class="hero">
        <h1>Welcome to <?php echo APP_NAME; ?></h1>
        <p>This is a basic Core PHP project structure for your application.</p>
        <a href="#" class="btn">Get Started</a>
    </section>

    <section class="grid">
        <div class="card">
            <h3>Dashboard</h3>
            <p>Track your income, expenses, and tax records in one place.</p>
        </div>
        <div class="card">
            <h3>Invoices</h3>
            <p>Create and manage customer invoices with a simple workflow.</p>
        </div>
        <div class="card">
            <h3>Reports</h3>
            <p>Generate tax-friendly summaries and professional reports.</p>
        </div>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
