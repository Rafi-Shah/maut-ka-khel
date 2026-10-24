/**
 * Supabase Project Configuration
 * 
 * You can set your Supabase Project URL and Anon Key here,
 * or configure it directly in the Admin Portal UI.
 */

export const DEFAULT_SUPABASE_CONFIG = {
  // Replace these with your project values from Supabase (Project Settings > API)
  url: "",
  anonKey: ""
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
