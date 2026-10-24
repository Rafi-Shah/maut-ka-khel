/**
 * Supabase Data Client with Real-time Subscriptions & Offline Fallback
 */

import { createClient } from "https://esm.sh/@supabase/supabase-js@2";
import { getSupabaseConfig } from "./config.js";

// Default Initial Seed Data (used for initial setup or offline fallback)
export const INITIAL_DATA = {
  courses: [
    { id: 1, code: 'pe',    name: 'Professional English',     color: '#d8735a', dim_color: '#3a2620', sort_order: 1 },
    { id: 2, code: 'mllab', name: 'Machine Learning lab',     color: '#e3a13d', dim_color: '#3a2c14', sort_order: 2 },
    { id: 3, code: 'dc',    name: 'Data communication',        color: '#3fa796', dim_color: '#173430', sort_order: 3 },
    { id: 4, code: 'dclab', name: 'Data communication lab',    color: '#63c2b4', dim_color: '#173430', sort_order: 4 },
    { id: 5, code: 'idp',   name: 'IDP II',                    color: '#8f7fe8', dim_color: '#241f3d', sort_order: 5 },
    { id: 6, code: 'ml',    name: 'Machine Learning',         color: '#d8578f', dim_color: '#341c29', sort_order: 6 },
  ],
  tasks: [
    { id: 1,  task_uid: 'pe-speaking',                course_code: 'pe',    title: 'Speaking test (video submission)', deadline_date: '2026-08-15', is_done: false },
    { id: 2,  task_uid: 'pe-ct2',                     course_code: 'pe',    title: 'CT-2',                             deadline_date: '2026-08-16', is_done: false },
    { id: 3,  task_uid: 'pe-presentation',            course_code: 'pe',    title: 'Presentation',                     deadline_date: '2026-08-17', is_done: false },
    { id: 4,  task_uid: 'idp-lab34',                  course_code: 'idp',   title: 'Lab report 3, 4',                  deadline_date: '2026-08-18', is_done: false },
    { id: 5,  task_uid: 'idp-poster-submit',          course_code: 'idp',   title: 'Poster Submit',                    deadline_date: '2026-08-18', is_done: false },
    { id: 6,  task_uid: 'ml-presentation',            course_code: 'ml',    title: 'Presentation',                     deadline_date: '2026-08-20', is_done: false },
    { id: 7,  task_uid: 'dc-presentation',            course_code: 'dc',    title: 'Presentation',                     deadline_date: '2026-08-21', is_done: false },
    { id: 8,  task_uid: 'dclab-lab2',                 course_code: 'dclab', title: 'Lab report 2',                     deadline_date: '2026-08-21', is_done: false },
    { id: 9,  task_uid: 'pe-listening',               course_code: 'pe',    title: 'Listening test',                   deadline_date: '2026-08-23', is_done: false },
    { id: 10, task_uid: 'ml-ct2',                     course_code: 'ml',    title: 'CT 2',                             deadline_date: '2026-08-23', is_done: false },
    { id: 11, task_uid: 'idp-lab5',                   course_code: 'idp',   title: 'Lab report 5',                     deadline_date: '2026-08-25', is_done: false },
    { id: 12, task_uid: 'idp-promo',                  course_code: 'idp',   title: 'Promo video',                      deadline_date: '2026-08-26', is_done: false },
    { id: 13, task_uid: 'dclab-project-presentation', course_code: 'dclab', title: 'Project Presentation',             deadline_date: '2026-08-28', is_done: false },
    { id: 14, task_uid: 'idp-final-report',           course_code: 'idp',   title: 'Final project report',             deadline_date: '2026-08-30', is_done: false },
    { id: 15, task_uid: 'mllab-lab-final',            course_code: 'mllab', title: 'Lab Final',                        deadline_date: '2026-08-30', is_done: false },
    { id: 16, task_uid: 'dclab-lab-final',             course_code: 'dclab', title: 'Lab Final',                        deadline_date: '2026-08-31', is_done: false },
    { id: 17, task_uid: 'dclab-project-report',       course_code: 'dclab', title: 'Project report',                  deadline_date: '2026-09-01', is_done: false },
    { id: 18, task_uid: 'idp-poster-presentation',    course_code: 'idp',   title: 'Poster Presentation',              deadline_date: '2026-09-01', is_done: false },
    { id: 19, task_uid: 'dc-assignment',              course_code: 'dc',    title: 'Assignment',                       deadline_date: '2026-09-02', is_done: false },
    { id: 20, task_uid: 'mllab-project-presentation', course_code: 'mllab', title: 'Project Presentation',             deadline_date: '2026-09-03', is_done: false },
    { id: 21, task_uid: 'mllab-labs123',              course_code: 'mllab', title: 'Lab report 1, 2, 3',               deadline_date: '2026-09-03', is_done: false },
    { id: 22, task_uid: 'mllab-conference-paper',     course_code: 'mllab', title: 'Conference Paper',                 deadline_date: '2026-09-03', is_done: false },
  ],
  settings: {
    eyebrow: 'Summer term · Aug – Sep 2026',
    hero_title: 'Everything due,<br>in <em>one line</em>.',
    hero_subtitle: 'Semester routine & deadline ledger tracker',
    allow_public_toggle: '1',
    admin_passcode: 'admin123',
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
  const sort_order = Date.now();
  let newCourse = null;

  if (client) {
    const { data, error } = await client.from('courses').insert([
      { code, name, color, dim_color, sort_order }
    ]).select();
    if (error) throw error;
    newCourse = data[0];
  } else {
    newCourse = { id: Date.now(), code, name, color, dim_color, sort_order };
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
