import { MoonIcon, SunIcon } from '../../assets/icons';
import { useTheme } from '../../context/ThemeContext';

/**
 * Pill-shaped theme toggle. Shows a Moon icon in light mode and a Sun icon in
 * dark mode. Clicking switches the theme (persisted by ThemeProvider).
 */
export default function ThemeToggle() {
  const { isDark, toggle } = useTheme();

  return (
    <button
      type="button"
      className="theme-toggle"
      onClick={toggle}
      title={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
      aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
    >
      {isDark ? <SunIcon size={20} /> : <MoonIcon size={20} />}
    </button>
  );
}
