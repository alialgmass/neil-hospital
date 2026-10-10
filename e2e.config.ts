import type { E2EConfig } from 'e2e';
import { web } from '@e2e-dev/web';
import { opencodeConsole } from 'e2e/oauth/opencode-console';

export default {
  agents: {
    default: {
      model: opencodeConsole('go/deepseek-v4.1-flash'),
      system: 'You are a thorough QA agent for an Arabic hospital management system. Verify every outcome.',
    },
  },
  targets: [{
    engine: web(),
    app: {
      url: process.env.APP_URL ?? 'https://hospitalv3.test',
    },
  }],
  credentials: {
    admin: {
      username: 'admin@hospital.local',
      password: process.env.E2E_ADMIN_PASSWORD ?? 'password',
    },
  },
} satisfies E2EConfig;
