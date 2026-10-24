/**
 * Supabase Project Configuration
 * 
 * Default credentials for multi-device sync (PC, Phone, Vercel).
 * Custom values can also be overridden in the Admin Portal.
 */

export const DEFAULT_SUPABASE_CONFIG = {
  url: "https://ttsfmbshanyieohqwbey.supabase.co",
  anonKey: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InR0c2ZtYnNoYW55aWVvaHF3YmV5Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODcyMDg3NTAsImV4cCI6MjEwMjc4NDc1MH0.PQVE4NSh_W6YQbd7QelebEa0g7QW8_u-InNWM9GVHzU"
};

export function getSupabaseConfig() {
  const localUrl = localStorage.getItem('maut_supabase_url');
  const localKey = localStorage.getItem('maut_supabase_key');

  const url = (localUrl && localUrl.trim()) ? localUrl.trim() : DEFAULT_SUPABASE_CONFIG.url.trim();
  const anonKey = (localKey && localKey.trim()) ? localKey.trim() : DEFAULT_SUPABASE_CONFIG.anonKey.trim();

  const isConfigured = Boolean(url && anonKey && url.startsWith('http') && anonKey.length > 20);

  return { url, anonKey, isConfigured };
}

export function saveSupabaseConfig(url, anonKey) {
  localStorage.setItem('maut_supabase_url', url.trim());
  localStorage.setItem('maut_supabase_key', anonKey.trim());
}

export function clearSupabaseConfig() {
  localStorage.removeItem('maut_supabase_url');
  localStorage.removeItem('maut_supabase_key');
}
