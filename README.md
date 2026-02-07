# HRMS - Human Resource Management System

A production-ready, mobile-first HRMS web application built with Next.js, TypeScript, and Tailwind CSS.

## Features

- **Role-Based Access Control**: Admin, Manager, Employee (Internal), Employee (Remote)
- **Attendance Management**: Clock in/out, attendance tracking, calendar view
- **Leave Management**: Leave balances, request submission, approval workflow
- **Payroll Management**: Salary breakdown, payslip generation (Admin only)
- **Profile Management**: Employee details, timezone display
- **PWA Support**: Installable as a mobile app
- **Responsive Design**: Mobile-first, works seamlessly on desktop

## Tech Stack

- **Framework**: Next.js 14 (App Router)
- **Language**: TypeScript
- **Styling**: Tailwind CSS
- **UI Components**: Headless UI, Radix UI
- **Charts**: Recharts
- **Icons**: Lucide React
- **Date Handling**: date-fns, date-fns-tz

## Getting Started

### Prerequisites

- Node.js 18+ 
- npm or yarn

### Installation

1. Install dependencies:
```bash
npm install
```

2. Run the development server:
```bash
npm run dev
```

3. Open [http://localhost:3000](http://localhost:3000) in your browser

### Demo Credentials

- **Admin**: `admin001` / `password`
- **Manager**: `manager001` / `password`
- **Employee (Internal)**: `emp001` / `password`
- **Employee (Remote)**: `emp002` / `password`

## Project Structure

```
├── app/                    # Next.js app directory
│   ├── dashboard/         # Dashboard page
│   ├── attendance/        # Attendance page
│   ├── leave/             # Leave management page
│   ├── payroll/           # Payroll page (Admin only)
│   ├── profile/           # Profile page
│   ├── layout.tsx         # Root layout
│   └── page.tsx           # Login page
├── components/
│   ├── layout/            # Layout components (Sidebar, TopBar, BottomNav)
│   ├── pages/             # Page components
│   └── ui/                # Reusable UI components
├── contexts/              # React contexts (Auth)
├── lib/                   # Utility functions
├── types/                 # TypeScript type definitions
└── public/                # Static assets
```

## Key Features

### Mobile-First Design
- Bottom navigation on mobile devices
- Touch-friendly interface
- Responsive cards and tables
- Optimized for small screens

### Role-Based UI
- Conditional rendering based on user role
- Admin sees all employees and payroll
- Manager sees team attendance and leave approvals
- Employee sees personal dashboard

### Timezone Support
- Displays both employee and company timezone
- Automatic timezone conversion for remote employees
- Timezone-aware attendance tracking

### PWA Ready
- Installable on mobile devices
- Offline-capable (with service worker)
- App-like experience

**Note**: To enable PWA installation, add icon files (`icon-192.png` and `icon-512.png`) to the `public/` directory. You can generate these using tools like [PWA Asset Generator](https://github.com/onderceylan/pwa-asset-generator).

## Building for Production

```bash
npm run build
npm start
```

## Environment Variables

Create a `.env.local` file for environment-specific configuration:

```env
NEXT_PUBLIC_API_URL=your_api_url
```

## License

MIT

## Contributing

This is a production-ready template. Replace mock data with actual API calls and customize as needed for your organization.

