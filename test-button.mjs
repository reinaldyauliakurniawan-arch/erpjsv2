import { chromium } from "playwright";

const BASE_URL = "http://just-speak.test";
const EMAIL = "admin@justspeak.co.id";
const PASSWORD = "admin123";

const PAGES = [
    "/admin/dashboard",
    "/admin/students",
    "/admin/tutors",
    "/admin/enrollments",
    "/admin/class-sessions",
    "/admin/classrooms",
    "/admin/programs",
    "/admin/attendance",
    "/admin/schedule",
    "/admin/tracker",
    "/admin/settings",
    "/finance",
    "/finance/journals",
    "/finance/payroll",
    "/finance/accounts",
];

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext();
const errors = [];

// Login
const loginPage = await context.newPage();
await loginPage.goto(`${BASE_URL}/login`);
await loginPage.fill("input[name=email]", EMAIL);
await loginPage.fill("input[name=password]", PASSWORD);
await loginPage.click("button[type=submit]");
await loginPage.waitForTimeout(2000);
await loginPage.close();

// Test tiap halaman
for (const path of PAGES) {
    const page = await context.newPage();
    const pageErrors = [];

    page.on("console", (msg) => {
        if (msg.type() === "error") pageErrors.push(`[JS] ${msg.text()}`);
    });

    page.on("response", (res) => {
        if (res.status() >= 400 && !res.url().includes("favicon")) {
            pageErrors.push(`[HTTP ${res.status()}] ${res.url()}`);
        }
    });

    await page.goto(`${BASE_URL}${path}`, { waitUntil: "networkidle" });

    // Klik semua tombol
    const buttons = await page.$$("button:not([type=submit]), [type=submit]");
    for (const btn of buttons) {
        try {
            const text = await btn.innerText().catch(() => "?");
            const visible = await btn.isVisible();
            if (!visible) continue;

            await btn.click({ timeout: 2000 }).catch(() => {});
            await page.waitForTimeout(500);
        } catch {}
    }

    if (pageErrors.length) {
        errors.push({ path, errors: pageErrors });
    }

    await page.close();
}

await browser.close();

// Report
if (errors.length === 0) {
    console.log("✅ Tidak ada error ditemukan!");
} else {
    console.log(`\n❌ Error ditemukan di ${errors.length} halaman:\n`);
    for (const e of errors) {
        console.log(`📄 ${e.path}`);
        e.errors.forEach((err) => console.log(`   ${err}`));
        console.log("");
    }
}
