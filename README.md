# Maut Ka Khel — Semester Routine & Deadlines Management System

A dynamic semester routine and deadline tracker equipped with a full-featured Admin Control Portal. Built to deploy seamlessly on **Vercel** via **GitHub** and powered by **Supabase** for real-time cloud database synchronization.

---

## 🚀 How to Deploy to Vercel via GitHub (Step-by-Step)

### Step 1: Push your Code to GitHub
Open your terminal in this project folder and run:
```bash
git add .
git commit -m "Add Vercel support with Admin Control Portal and Supabase"
git push origin main
```

---

### Step 2: Deploy to Vercel
1. Go to [vercel.com](https://vercel.com) and log in with your GitHub account.
2. Click **"Add New..."** → **"Project"**.
3. Select your repository (`maut-ka-khel`) and click **"Deploy"**.
4. That's it! In less than 30 seconds, Vercel will give you a live HTTPS URL (e.g., `https://maut-ka-khel.vercel.app`).

---

### Step 3: Setup your Free Cloud Database (Supabase) in 2 Minutes
To sync data across all devices and let your Control Portal update the website in real-time:

1. Go to [supabase.com](https://supabase.com) and sign in (free).
2. Click **"New Project"** (give it any name, e.g. `maut-ka-khel`).
3. Once created, click on **SQL Editor** in the left sidebar.
4. Copy the entire contents of the `supabase_schema.sql` file in this project, paste it into the editor, and click **"Run"**.
   - *This creates all tables (`tasks`, `courses`, `settings`), enables real-time subscriptions, and seeds all 18 deadlines and 6 courses!*
5. Go to **Project Settings** (gear icon) → **API**:
   - Copy the **Project URL**.
   - Copy the **anon public API Key**.
6. Open your live Vercel website, go to the **Control Portal** (`/admin.html` or `/admin`), click **"Database Config"**, paste your URL & Key, and click **"Save & Connect"**!

---

## 🔑 Admin Portal Access & Credentials

- **Portal URL on Vercel:** `https://your-app.vercel.app/admin` (or `/admin.html`)
- **Default Passcode:** `admin123`
*(You can change your passcode anytime inside the portal under "Security & Passcode")*

---

## 🌟 Key Features

1. **Public Website (`index.html` / `/`)**:
   - High-end dark editorial theme (Fraunces, Space Grotesk, JetBrains Mono).
   - Real-time countdowns (`✓ Done`, `Due Today`, `1 day left`, `X days left`, `Overdue`).
   - Dynamic course badges with custom colors.
   - Live multi-user real-time sync via Supabase.
   - Interactive task completion toggle.

2. **Admin Control Portal (`admin.html` / `/admin`)**:
   - **Deadline Management**: Add, edit, delete, search, filter, and mark tasks as done.
   - **Course Management**: Add courses with short codes, pick accent badge colors, and manage active subjects.
   - **Site Settings**: Customize term/eyebrow header, headline title, subtitle, and visitor permissions.
   - **Cloud Database Configuration**: Easy on-screen Supabase setup with offline fallback mode.
   - **Security**: Customizable admin passcode.

---

## 📁 Project Structure

```
maut-ka-khel/
├── index.html           # Main live website (Vercel-ready)
├── admin.html           # Full Admin Control Portal (Vercel-ready)
├── js/
│   ├── config.js        # Supabase config loader & storage
│   └── supabase-client.js # Supabase client with real-time sync & offline fallback
├── supabase_schema.sql  # 1-click copy-paste SQL schema & seed data
├── vercel.json          # Vercel clean URL routes (/admin -> admin.html)
└── README.md            # Deployment guide
```
