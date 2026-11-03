import { describe, expect, it } from 'vitest';
import { moduleId } from '../src/lib/modules/languages';

describe('languages module', () => {
  it('exports its identifier', () => {
    expect(moduleId).toBe('languages');
  });
});
