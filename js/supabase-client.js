/**
 * Supabase Data Client with Real-time Subscriptions & Offline Fallback
 */

import { createClient } from "https://esm.sh/@supabase/supabase-js@2";
import { getSupabaseConfig } from "./config.js";

// Default Initial Seed Data (used before Supabase credentials or as fallback)
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
    { id: 16, task_uid: 'dclab-lab-final',            course_code: 'dclab', title: 'Lab Final',                        deadline_date: '2026-08-31', is_done: false },
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
    admin_passcode: 'admin123'
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
function getLocalStore() {
  const data = localStorage.getItem('maut_local_store');
  if (data) {
    try { return JSON.parse(data); } catch (e) {}
  }
  return JSON.parse(JSON.stringify(INITIAL_DATA));
}

function saveLocalStore(store) {
  localStorage.setItem('maut_local_store', JSON.stringify(store));
}

// Read All Data
export async function fetchAllData() {
  const client = getClient();
  if (!client) {
    return { ...getLocalStore(), isLive: false };
  }

  try {
    const [coursesRes, tasksRes, settingsRes] = await Promise.all([
      client.from('courses').select('*').order('sort_order', { ascending: true }),
      client.from('tasks').select('*').order('deadline_date', { ascending: true }),
      client.from('settings').select('*')
    ]);

    if (coursesRes.error || tasksRes.error) {
      throw coursesRes.error || tasksRes.error;
    }

    const settingsMap = { ...INITIAL_DATA.settings };
    (settingsRes.data || []).forEach(r => {
      settingsMap[r.setting_key] = r.setting_value;
    });

    const courses = coursesRes.data.length ? coursesRes.data : INITIAL_DATA.courses;
    const tasks = tasksRes.data.length ? tasksRes.data : INITIAL_DATA.tasks;

    return {
      courses,
      tasks,
      settings: settingsMap,
      isLive: true
    };
  } catch (err) {
    console.warn("Supabase fetch failed, using local store:", err);
    return { ...getLocalStore(), isLive: false };
  }
}

// Task Actions
export async function addTask({ course_code, title, deadline_date, is_done }) {
  const client = getClient();
  const task_uid = `${course_code}-${Date.now()}`;
  if (client) {
    const { data, error } = await client.from('tasks').insert([
      { task_uid, course_code, title, deadline_date, is_done: !!is_done }
    ]).select();
    if (error) throw error;
    return data[0];
  } else {
    const store = getLocalStore();
    const newTask = {
      id: Date.now(),
      task_uid,
      course_code,
      title,
      deadline_date,
      is_done: !!is_done
    };
    store.tasks.push(newTask);
    saveLocalStore(store);
    return newTask;
  }
}

export async function updateTask(id, { course_code, title, deadline_date, is_done }) {
  const client = getClient();
  if (client) {
    const payload = { course_code, title, deadline_date };
    if (is_done !== undefined) payload.is_done = is_done;
    const { error } = await client.from('tasks').update(payload).eq('id', id);
    if (error) throw error;
  } else {
    const store = getLocalStore();
    const idx = store.tasks.findIndex(t => t.id == id);
    if (idx !== -1) {
      store.tasks[idx] = { ...store.tasks[idx], course_code, title, deadline_date };
      if (is_done !== undefined) store.tasks[idx].is_done = is_done;
      saveLocalStore(store);
    }
  }
}

export async function toggleTaskDone(id, is_done) {
  const client = getClient();
  if (client) {
    const { error } = await client.from('tasks').update({ is_done }).eq('id', id);
    if (error) throw error;
  } else {
    const store = getLocalStore();
    const t = store.tasks.find(x => x.id == id);
    if (t) {
      t.is_done = is_done;
      saveLocalStore(store);
    }
  }
}

export async function deleteTask(id) {
  const client = getClient();
  if (client) {
    const { error } = await client.from('tasks').delete().eq('id', id);
    if (error) throw error;
  } else {
    const store = getLocalStore();
    store.tasks = store.tasks.filter(t => t.id != id);
    saveLocalStore(store);
  }
}

// Course Actions
export async function addCourse({ code, name, color, dim_color }) {
  const client = getClient();
  const sort_order = Date.now();
  if (client) {
    const { data, error } = await client.from('courses').insert([
      { code, name, color, dim_color, sort_order }
    ]).select();
    if (error) throw error;
    return data[0];
  } else {
    const store = getLocalStore();
    const newCourse = { id: Date.now(), code, name, color, dim_color, sort_order };
    store.courses.push(newCourse);
    saveLocalStore(store);
    return newCourse;
  }
}

export async function updateCourse(id, { code, name, color, dim_color }) {
  const client = getClient();
  if (client) {
    const { error } = await client.from('courses').update({ code, name, color, dim_color }).eq('id', id);
    if (error) throw error;
  } else {
    const store = getLocalStore();
    const idx = store.courses.findIndex(c => c.id == id);
    if (idx !== -1) {
      store.courses[idx] = { ...store.courses[idx], code, name, color, dim_color };
      saveLocalStore(store);
    }
  }
}

export async function deleteCourse(id, code) {
  const client = getClient();
  if (client) {
    // Delete linked tasks first
    await client.from('tasks').delete().eq('course_code', code);
    const { error } = await client.from('courses').delete().eq('id', id);
    if (error) throw error;
  } else {
    const store = getLocalStore();
    store.courses = store.courses.filter(c => c.id != id);
    store.tasks = store.tasks.filter(t => t.course_code !== code);
    saveLocalStore(store);
  }
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
  } else {
    const store = getLocalStore();
    store.settings = { ...store.settings, ...settingsObj };
    saveLocalStore(store);
  }
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
