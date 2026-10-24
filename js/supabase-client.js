/**
 * Supabase Data Client with Real-time Subscriptions & Offline Fallback
 */

import { createClient } from "https://esm.sh/@supabase/supabase-js@2";
import { getSupabaseConfig } from "./config.js";

// Default Initial Seed Data (used for initial setup or offline fallback)
export const INITIAL_DATA = {
  courses: [
    { id: 7,  code: 'eee 301', name: 'Electrical Drives and Instrumentations', color: '#e3a13d', dim_color: 'rgb(57, 40, 15)', sort_order: 1 },
    { id: 8,  code: 'ged 301', name: 'History of Emergence of Bangladesh',    color: '#e33b3b', dim_color: 'rgb(57, 15, 15)', sort_order: 2 },
    { id: 9,  code: 'cse 413', name: 'Natural Language Processing',            color: '#3b7be3', dim_color: 'rgb(15, 31, 57)', sort_order: 3 },
    { id: 10, code: 'cse 414', name: 'Natural Language Processing Lab',        color: '#7224b2', dim_color: 'rgb(29, 9, 45)',  sort_order: 4 },
    { id: 11, code: 'cse 401', name: 'Operating Systems',                     color: '#75d25b', dim_color: 'rgb(29, 53, 23)', sort_order: 5 },
    { id: 12, code: 'cse 402', name: 'Operating Systems Lab',                 color: '#219c8d', dim_color: 'rgb(8, 39, 35)',  sort_order: 6 },
  ],
  tasks: [
    { id: 26, task_uid: 'cse 413-1792859346793', course_code: 'cse 413', title: 'CT - 1 & Portfolio', deadline_date: '2026-10-27', is_done: false },
    { id: 25, task_uid: 'cse 402-1792859292736', course_code: 'cse 402', title: 'CLP - 1',            deadline_date: '2026-10-28', is_done: false },
    { id: 24, task_uid: 'eee 301-1792859159213', course_code: 'eee 301', title: 'CT - 1',            deadline_date: '2026-10-31', is_done: false },
  ],
  settings: {
    eyebrow: 'Fall term · Sep – Jan 2027',
    hero_title: 'Everything due,<br>in <em>one line</em>.',
    hero_subtitle: 'Semester routine & deadline ledger tracker',
    allow_public_toggle: '1',
    admin_passcode: 'rafi777',
    db_initialized: 'true'
  }
};

let supabaseClient = null;

export function getClient() {
  if (supabaseClient) return supabaseClient;
  const config = getSupabaseConfig();
  if (config.isConfigured) {
    try {
      supabaseClient = createClient(config.url, config.anonKey);
      return supabaseClient;
    } catch (e) {
      console.warn("Could not initialize Supabase client:", e);
    }
  }
  return null;
}

// In-memory or localStorage cache for offline/standalone mode
export function getLocalStore() {
  const data = localStorage.getItem('maut_local_store');
  if (data) {
    try {
      const parsed = JSON.parse(data);
      if (parsed && Array.isArray(parsed.courses) && Array.isArray(parsed.tasks)) {
        return parsed;
      }
    } catch (e) {}
  }
  const initial = JSON.parse(JSON.stringify(INITIAL_DATA));
  localStorage.setItem('maut_local_store', JSON.stringify(initial));
  return initial;
}

export function saveLocalStore(store) {
  localStorage.setItem('maut_local_store', JSON.stringify(store));
}

// Auto-seed Supabase database if tables exist but are empty
export async function seedSupabaseDatabase(client) {
  if (!client) throw new Error("Supabase client is not initialized");

  // 1. Seed courses
  const coursesPayload = INITIAL_DATA.courses.map(c => ({
    code: c.code,
    name: c.name,
    color: c.color,
    dim_color: c.dim_color,
    sort_order: c.sort_order
  }));
  const { error: coursesErr } = await client.from('courses').upsert(coursesPayload, { onConflict: 'code' });
  if (coursesErr) throw coursesErr;

  // 2. Seed settings
  const settingsRows = Object.entries(INITIAL_DATA.settings).map(([k, v]) => ({
    setting_key: k,
    setting_value: String(v)
  }));
  const { error: settingsErr } = await client.from('settings').upsert(settingsRows, { onConflict: 'setting_key' });
  if (settingsErr) throw settingsErr;

  // 3. Check if tasks already exist
  const { data: existingTasks } = await client.from('tasks').select('id').limit(1);
  if (!existingTasks || existingTasks.length === 0) {
    const tasksPayload = INITIAL_DATA.tasks.map(t => ({
      task_uid: t.task_uid,
      course_code: t.course_code,
      title: t.title,
      deadline_date: t.deadline_date,
      is_done: !!t.is_done
    }));
    const { error: tasksErr } = await client.from('tasks').insert(tasksPayload);
    if (tasksErr) throw tasksErr;
  }
}

// Reset entire database to default initial state
export async function resetDatabaseToDefaults() {
  const client = getClient();
  if (client) {
    // Delete existing rows
    await client.from('tasks').delete().neq('id', -1);
    await client.from('courses').delete().neq('id', -1);
    await seedSupabaseDatabase(client);
  }
  const initial = JSON.parse(JSON.stringify(INITIAL_DATA));
  saveLocalStore(initial);
  return initial;
}

// Read All Data
export async function fetchAllData() {
  const client = getClient();
  if (!client) {
    return { ...getLocalStore(), isLive: false, status: 'standalone' };
  }

  try {
    const [coursesRes, tasksRes, settingsRes] = await Promise.all([
      client.from('courses').select('*').order('sort_order', { ascending: true }),
      client.from('tasks').select('*').order('deadline_date', { ascending: true }),
      client.from('settings').select('*')
    ]);

    if (coursesRes.error) throw coursesRes.error;
    if (tasksRes.error) throw tasksRes.error;

    const settingsMap = { ...INITIAL_DATA.settings };
    (settingsRes.data || []).forEach(r => {
      settingsMap[r.setting_key] = r.setting_value;
    });

    let courses = coursesRes.data || [];
    let tasks = tasksRes.data || [];

    // If Supabase database is completely unseeded, auto-seed initial data
    const hasAnyRows = courses.length > 0 || tasks.length > 0 || (settingsRes.data && settingsRes.data.length > 0);
    const isInitMarked = settingsMap.db_initialized === 'true';

    if (!hasAnyRows && !isInitMarked) {
      try {
        console.log("Supabase database empty. Auto-seeding initial defaults...");
        await seedSupabaseDatabase(client);
        const [freshCourses, freshTasks] = await Promise.all([
          client.from('courses').select('*').order('sort_order', { ascending: true }),
          client.from('tasks').select('*').order('deadline_date', { ascending: true })
        ]);
        courses = freshCourses.data || INITIAL_DATA.courses;
        tasks = freshTasks.data || INITIAL_DATA.tasks;
      } catch (seedErr) {
        console.warn("Auto-seeding Supabase failed:", seedErr);
        return { ...getLocalStore(), isLive: false, error: seedErr.message, status: 'error' };
      }
    }

    // Keep local cache in sync as offline backup
    saveLocalStore({ courses, tasks, settings: settingsMap });

    return {
      courses,
      tasks,
      settings: settingsMap,
      isLive: true,
      status: 'connected'
    };
  } catch (err) {
    console.warn("Supabase fetch failed, falling back to local store:", err);
    return { ...getLocalStore(), isLive: false, error: err.message, status: 'error' };
  }
}

// Task Actions
export async function addTask({ course_code, title, deadline_date, is_done }) {
  const client = getClient();
  const task_uid = `${course_code}-${Date.now()}`;
  let newTask = null;

  if (client) {
    const { data, error } = await client.from('tasks').insert([
      { task_uid, course_code, title, deadline_date, is_done: !!is_done }
    ]).select();
    if (error) throw error;
    newTask = data[0];
  } else {
    newTask = {
      id: Date.now(),
      task_uid,
      course_code,
      title,
      deadline_date,
      is_done: !!is_done
    };
  }

  // Update local cache
  const store = getLocalStore();
  store.tasks.push(newTask);
  saveLocalStore(store);

  return newTask;
}

export async function updateTask(id, { course_code, title, deadline_date, is_done }) {
  const client = getClient();
  const payload = { course_code, title, deadline_date };
  if (is_done !== undefined) payload.is_done = is_done;

  if (client) {
    const isNum = typeof id === 'number' || (!isNaN(Number(id)) && String(Number(id)) === String(id).trim());
    let error = null;
    if (isNum) {
      const res = await client.from('tasks').update(payload).eq('id', Number(id));
      error = res.error;
    } else {
      const res = await client.from('tasks').update(payload).eq('task_uid', String(id));
      error = res.error;
    }
    if (error) throw error;
  }

  // Update local cache
  const store = getLocalStore();
  const idx = store.tasks.findIndex(t => t.id == id || t.task_uid == id);
  if (idx !== -1) {
    store.tasks[idx] = { ...store.tasks[idx], ...payload };
    saveLocalStore(store);
  }
}

export async function toggleTaskDone(id, is_done) {
  const client = getClient();
  if (client) {
    const isNum = typeof id === 'number' || (!isNaN(Number(id)) && String(Number(id)) === String(id).trim());
    let error = null;
    if (isNum) {
      const res = await client.from('tasks').update({ is_done }).eq('id', Number(id));
      error = res.error;
    } else {
      const res = await client.from('tasks').update({ is_done }).eq('task_uid', String(id));
      error = res.error;
    }
    if (error) throw error;
  }

  // Update local cache
  const store = getLocalStore();
  const t = store.tasks.find(x => x.id == id || x.task_uid == id);
  if (t) {
    t.is_done = is_done;
    saveLocalStore(store);
  }
}

export async function deleteTask(id) {
  const client = getClient();
  if (client) {
    const isNum = typeof id === 'number' || (!isNaN(Number(id)) && String(Number(id)) === String(id).trim());
    let error = null;
    if (isNum) {
      const res = await client.from('tasks').delete().eq('id', Number(id));
      error = res.error;
    } else {
      const res = await client.from('tasks').delete().eq('task_uid', String(id));
      error = res.error;
    }
    if (error) throw error;
  }

  // Always update local cache
  const store = getLocalStore();
  store.tasks = store.tasks.filter(t => t.id != id && t.task_uid != id);
  saveLocalStore(store);
}

// Course Actions
export async function addCourse({ code, name, color, dim_color }) {
  const client = getClient();
  let newCourse = null;

  if (client) {
    // Generate next sequential integer for sort_order (fits in standard INT)
    let nextSort = 1;
    try {
      const { data: courses } = await client
        .from('courses')
        .select('sort_order')
        .order('sort_order', { ascending: false })
        .limit(1);
      if (courses && courses.length > 0 && typeof courses[0].sort_order === 'number') {
        nextSort = courses[0].sort_order + 1;
      }
    } catch (e) {
      nextSort = Math.floor(Date.now() / 1000) % 100000;
    }

    const { data, error } = await client.from('courses').insert([
      { code, name, color, dim_color, sort_order: nextSort }
    ]).select();
    if (error) throw error;
    newCourse = data[0];
  } else {
    const store = getLocalStore();
    const nextSort = store.courses.length + 1;
    newCourse = { id: Date.now(), code, name, color, dim_color, sort_order: nextSort };
  }

  const store = getLocalStore();
  store.courses.push(newCourse);
  saveLocalStore(store);
  return newCourse;
}

export async function updateCourse(id, { code, name, color, dim_color }) {
  const client = getClient();
  if (client) {
    const isNum = typeof id === 'number' || (!isNaN(Number(id)) && String(Number(id)) === String(id).trim());
    let error = null;
    if (isNum) {
      const res = await client.from('courses').update({ code, name, color, dim_color }).eq('id', Number(id));
      error = res.error;
    } else {
      const res = await client.from('courses').update({ code, name, color, dim_color }).eq('code', String(code));
      error = res.error;
    }
    if (error) throw error;
  }

  const store = getLocalStore();
  const idx = store.courses.findIndex(c => c.id == id || c.code === code);
  if (idx !== -1) {
    store.courses[idx] = { ...store.courses[idx], code, name, color, dim_color };
    saveLocalStore(store);
  }
}

export async function deleteCourse(id, code) {
  const client = getClient();
  if (client) {
    // Delete linked tasks first
    await client.from('tasks').delete().eq('course_code', code);
    const isNum = typeof id === 'number' || (!isNaN(Number(id)) && String(Number(id)) === String(id).trim());
    let error = null;
    if (isNum) {
      const res = await client.from('courses').delete().eq('id', Number(id));
      error = res.error;
    } else {
      const res = await client.from('courses').delete().eq('code', String(code));
      error = res.error;
    }
    if (error) throw error;
  }

  const store = getLocalStore();
  store.courses = store.courses.filter(c => c.id != id && c.code !== code);
  store.tasks = store.tasks.filter(t => t.course_code !== code);
  saveLocalStore(store);
}

// Settings Actions
export async function saveSettings(settingsObj) {
  const client = getClient();
  if (client) {
    for (const [key, value] of Object.entries(settingsObj)) {
      const { error } = await client.from('settings').upsert({
        setting_key: key,
        setting_value: String(value)
      });
      if (error) throw error;
    }
  }

  const store = getLocalStore();
  store.settings = { ...store.settings, ...settingsObj };
  saveLocalStore(store);
}

// Real-time listener
export function subscribeToChanges(callback) {
  const client = getClient();
  if (!client) return null;

  try {
    const channel = client.channel('public:maut_changes')
      .on('postgres_changes', { event: '*', schema: 'public', table: 'tasks' }, () => callback('tasks'))
      .on('postgres_changes', { event: '*', schema: 'public', table: 'courses' }, () => callback('courses'))
      .on('postgres_changes', { event: '*', schema: 'public', table: 'settings' }, () => callback('settings'))
      .subscribe();

    return channel;
  } catch (err) {
    console.warn("Realtime subscription error:", err);
    return null;
  }
}
