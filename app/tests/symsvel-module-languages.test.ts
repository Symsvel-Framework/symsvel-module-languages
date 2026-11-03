import { describe, expect, it } from 'vitest';
import { moduleId } from '../src/lib/modules/symsvel-module-languages';

describe('symsvel-module-languages module', () => {
  it('exports its identifier', () => {
    expect(moduleId).toBe('symsvel-module-languages');
  });
});
