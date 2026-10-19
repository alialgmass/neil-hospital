import { test } from '@e2e-dev/web';
import { credentials, expect } from 'e2e';

test('an admin signs in and reaches the dashboard', async ({ app, screen, browser }) => {
  const admin = credentials.user('admin');

  await app.open('/login');
  await screen.getByLabel('اسم المستخدم أو البريد الإلكتروني').fill(admin.username);
  await screen.getByLabel('كلمة المرور').fill(admin.password);
  await screen.getByRole('button', { name: 'تسجيل الدخول' }).click();

  await expect(browser).not.toHaveURL(/\/login/);
  await expect(screen.getByLabel('اسم المستخدم أو البريد الإلكتروني')).toBeHidden();
});
