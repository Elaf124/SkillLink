# Methodology

## 1. Development Approach

SkillLink was developed using an iterative and agile approach. The platform was divided into manageable feature areas, and each area was implemented, checked, and refined before the next area was expanded. This made it possible to respond to workflow and integration issues while development was in progress.

The system was built in stages. The initial stages focused on authentication, user roles, profiles, services, and the basic marketplace workflow. Later stages added jobs, offers, bookings, messaging, notifications, favorites, provider verification, milestones, time logs, payments, escrow behavior, and administrative dashboards. This staged approach reduced the risk of changing several connected workflows at once.

## 2. Requirements Analysis

Requirements were identified from the activities that each platform participant must complete. Customers need to find providers, review services, post jobs, receive offers, communicate with providers, create bookings, approve completed work, and make payments. Providers need to create profiles and services, submit verification documents, respond to jobs, manage bookings, deliver work, record time or milestones, and receive simulated payouts.

Support administrators need to review provider verification documents, manage disputes, and handle user reports. Finance administrators need to monitor payments, escrow balances, transactions, and provider payout readiness. These needs were translated into role-specific pages, API endpoints, database records, and authorization rules.

The main workflows were defined as connected sequences: registration and email verification; provider verification; service discovery; job posting and offer submission; customer-provider messaging; booking acceptance and delivery; customer confirmation; payment and escrow release; notifications; dispute handling; and finance administration. Defining these workflows first helped keep the frontend, API, and database behavior consistent.

## 3. System Design

SkillLink uses a separated frontend and backend design. The frontend is implemented with Nuxt and Vue and provides pages, reusable components, forms, dashboards, navigation, and client-side interaction. The backend is implemented with Laravel and exposes a REST-style API for authentication, marketplace operations, administration, payments, messaging, notifications, and file management. A relational database stores users, profiles, marketplace data, financial records, and audit-related information.

The system uses role-based access for customers, providers, support administrators, and finance administrators. Authentication identifies the current user, while role middleware and controller-level checks restrict actions to permitted users. For example, customers can create jobs and approve work, providers can submit offers and deliver work, support administrators can review disputes and verification records, and finance administrators can view financial operations.

The database relationships reflect the business domain. Users may have customer or provider profiles and may own jobs, bookings, messages, notifications, favorites, wallets, and reports. Jobs can receive offers and lead to bookings. Bookings connect customers, providers, services, payments, milestones, time logs, disputes, and booking history. Provider profiles connect services, skills, portfolios, availability, verification documents, and payout methods.

## 4. Frontend Development

The Nuxt/Vue frontend was organized into pages, layouts, components, composables, and middleware. Pages represent major user areas such as authentication, marketplace browsing, jobs, bookings, messages, notifications, provider wallet management, and administration. Reusable components and composables centralize forms, API calls, shared state, and repeated interface behavior.

Responsive design was used so that marketplace pages, forms, dashboards, navigation, and booking workflows can be used on different screen sizes. The interface includes role-aware navigation, customer and provider dashboards, administrative views, service and job forms, booking action panels, messaging interfaces, notification lists, verification uploads, and wallet views. Validation messages, status indicators, loading states, and workflow-specific actions were included to improve usability and reduce incorrect submissions.

## 5. Backend Development

The backend was developed as a Laravel API. Routes in `routes/api.php` map HTTP requests to controllers grouped by areas such as authentication, identity, marketplace, administration, and finance. Controllers coordinate validation, authorization, model operations, state transitions, notifications, and JSON responses.

Laravel Sanctum is used for token-based authentication. Middleware protects authenticated endpoints, verifies email status, and checks roles. Controllers also verify ownership and participation in sensitive operations, such as accessing a conversation, changing a booking, approving a milestone, or viewing a provider's financial data. Laravel validation rules check required fields, formats, permitted status values, numeric limits, file types, and relationships to existing records.

Eloquent models represent the main business entities and define relationships such as `hasMany`, `belongsTo`, and `hasOne`. These relationships allow related users, profiles, services, bookings, payments, messages, and administrative records to be loaded and updated through a consistent domain model.

## 6. Database Design

The database schema was created and evolved through Laravel migrations. Migrations define tables, columns, indexes, foreign keys, unique constraints, nullable fields, and later schema changes without requiring manual edits to the database structure. Seeders and factories provide initial categories, roles, users, provider profiles, and other development data where required.

The schema contains tables for users and roles; customer and provider profiles; services, skills, portfolios, availability, and verification documents; jobs and attachments; offers and bookings; booking history, milestones, and time logs; payments, wallets, wallet transactions, payouts, and payout methods; conversations and messages; notifications, favorites, disputes, and user reports.

These relationships provide persistent storage for the complete platform workflow. Job attachments and provider portfolios are associated with their owners, bookings connect the two parties to an agreed service, payments and wallet transactions record financial state, messages preserve communication, and favorites preserve a customer's provider shortlist across sessions.

## 7. Payment and Escrow Simulation

The payment workflow was implemented as a simulation of local payment methods such as Telebirr, CBE Birr, and Chapa. No live gateway transaction is performed. Instead, the application records the selected payment method, creates payment and transaction records, and updates the corresponding simulated financial state.

Payment states are used to represent the lifecycle of a transaction, including pending, successful or paid, failed, refunded, and released states where applicable. Funds are treated as being held in escrow until the booking reaches the required completion and confirmation stages. After customer confirmation, the simulated escrow amount can be released, the platform fee can be separated, and the provider wallet can be credited.

Refund and cancellation paths update the payment state and record the resulting wallet or transaction changes. Provider payout readiness and payout methods are also represented in the finance workflow, but actual disbursement to Telebirr, CBE, or another external provider is simulated rather than executed through a live gateway.

## 8. File Upload and Storage

File uploads use Laravel's storage system and the configured public disk. Customers can attach files to jobs, providers can add portfolio items, and providers can upload verification documents for administrative review. Uploaded files are validated by type and size before being stored, and the related database record retains the file path or URL and its business metadata.

Verification documents have a review status, while pending documents can be removed according to the workflow rules. Storage links and API responses make approved files available to the appropriate frontend views without exposing unrelated users' files through unprotected application actions.

## 9. Testing and Validation

Testing and validation were performed at several levels. API route testing checks authentication, response codes, request payloads, state transitions, and resource ownership. Form validation checks required fields, allowed values, numeric ranges, email and password rules, and upload restrictions before data is persisted.

Role-access testing verifies that customers, providers, support administrators, and finance administrators can access only the operations assigned to them. The Laravel test suite and application-level checks are used for backend behavior, while frontend build testing checks that the Nuxt/Vue application compiles and that API integration points are valid.

Manual workflow testing was used for end-to-end scenarios such as registration and verification, provider document review, job and offer creation, booking acceptance, messaging, completion confirmation, simulated payment, escrow release, notification delivery, milestone approval, and payout readiness. Negative cases such as unauthorized access, invalid status transitions, duplicate actions, and non-participant access were also checked.

## 10. Security Measures

Authentication is enforced through Laravel Sanctum tokens, and email verification is required before users can perform meaningful platform actions. Role-based authorization middleware protects administrative and role-specific routes. Controllers additionally scope queries to the authenticated user or the relevant booking, conversation, provider profile, or wallet.

Passwords are handled through Laravel's password hashing facilities rather than being stored in plain text. Password reset and email verification codes are time-limited and stored securely. Protected API routes reject unauthenticated requests, while validation prevents malformed, unexpected, or out-of-range input from being processed.

File validation restricts upload types and sizes. Sensitive payout account numbers are masked in responses, and financial and administrative records are available only through authorized routes. These measures provide application-level protection for the simulated environment, while production deployment would require additional infrastructure and operational controls.

## 11. Deployment or Local Environment

Development was performed in a local environment using PHP, Laravel, Composer, Node.js, npm, and a configured relational database. The Laravel API runs locally through the Artisan development server, while the Nuxt/Vue frontend runs as a separate development application. The frontend communicates with the backend through the configured API base URL and cross-origin settings.

Database connection details, application keys, mail settings, storage configuration, and other environment-specific values are supplied through the environment configuration. Migrations create the database schema, and storage linking makes public uploads available during development. Before deployment, the same configuration must be replaced with production database credentials, mail services, secure secrets, HTTPS, and an appropriate web server or hosting platform.

## 12. Limitations

The main limitation is that payment processing is simulated. The project does not use live Telebirr, CBE Birr, or Chapa gateway credentials, so it cannot confirm real external transactions or send actual provider payouts. Escrow, release, refund, wallet, and payout behavior demonstrate the intended workflow using internal records only.

Other production features remain future work, including live payment gateway integration, webhook verification, automated disbursement, production-grade monitoring, infrastructure hardening, and operational support processes. These limitations do not prevent evaluation of the platform's core workflows, but they must be addressed before the system is used for real financial transactions or public production deployment.