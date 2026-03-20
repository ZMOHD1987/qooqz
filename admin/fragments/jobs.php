<?php
declare(strict_types=1);
/**
 * /admin/fragments/jobs.php
 * Complete Jobs Management Workspace — Full Rebuild
 */

$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

if (!is_admin_logged_in()) {
    if ($isFragment) { http_response_code(401); echo json_encode(['error' => 'Not authenticated']); exit; }
    else { header('Location: /admin/login.php'); exit; }
}

$user     = admin_user();
$lang     = admin_lang();
$dir      = in_array($lang, ['ar','he','fa','ur']) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

$canCreate  = can_create('jobs')  || can('jobs.manage');
$canEdit    = can_edit_all('jobs') || can_edit_own('jobs') || can('jobs.manage');
$canDelete  = can_delete_all('jobs') || can_delete_own('jobs') || can('jobs.manage');
$canView    = can_view_all('jobs') || can_view_own('jobs') || can_view_tenant('jobs');

if (!$canView && !is_super_admin()) {
    if ($isFragment) { http_response_code(403); echo json_encode(['error' => 'Access denied']); exit; }
    http_response_code(403); die('Access denied');
}

function __t(string $key, string $fallback = ''): string {
    if (function_exists('i18n_get')) { $v = i18n_get($key); return $v ?? ($fallback ?: $key); }
    return $fallback ?: $key;
}
?>
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/jobs.css?v=<?= time() ?>">
<?php endif; ?>
<meta data-page="jobs" data-i18n-files="/languages/Jobs/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="jobsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

<?php if (!$isFragment): ?>
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title" data-i18n="jobs.title"><?= __t('jobs.title','Jobs Management') ?></h1>
        <p class="page-subtitle" data-i18n="jobs.subtitle"><?= __t('jobs.subtitle','Manage jobs, applications, interviews, alerts and questions') ?></p>
    </div>
</div>
<?php endif; ?>

<!-- ═══ WORKSPACE TABS ═══ -->
<div class="workspace-tabs" id="workspaceTabs">
    <button class="tab-btn active" data-tab="jobs"><i class="fas fa-briefcase"></i> <span data-i18n="workspace.tabs.jobs">Jobs</span></button>
    <button class="tab-btn" data-tab="applications"><i class="fas fa-file-alt"></i> <span data-i18n="workspace.tabs.applications">Applications</span></button>
    <button class="tab-btn" data-tab="interviews"><i class="fas fa-calendar-check"></i> <span data-i18n="workspace.tabs.interviews">Interviews</span></button>
    <button class="tab-btn" data-tab="alerts"><i class="fas fa-bell"></i> <span data-i18n="workspace.tabs.alerts">Alerts</span></button>
    <button class="tab-btn" data-tab="questions"><i class="fas fa-question-circle"></i> <span data-i18n="workspace.tabs.questions">Questions</span></button>
</div>

<!-- ══════════════════════════════════════════
     TAB: JOBS
══════════════════════════════════════════ -->
<div id="jobsTab" class="ws-panel active">

    <!-- JOB FORM -->
    <div id="jobFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="jobFormTitle" data-i18n="form.add_title"><?= __t('form.add_title','Add Job') ?></h3>
            <button class="btn btn-sm btn-outline" id="jobCloseForm"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="jobForm" novalidate>
                <input type="hidden" id="jobId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="jobEntityId" name="entity_id">
                <input type="hidden" id="jobCreatedBy" name="created_by" value="<?= $userId ?>">

                <!-- Inner form tabs -->
                <div class="form-tabs" id="jobFormTabs">
                    <button type="button" class="ftab-btn active" data-ftab="basic"><i class="fas fa-info-circle"></i> <span data-i18n="form.tabs.basic">Basic</span></button>
                    <button type="button" class="ftab-btn" data-ftab="details"><i class="fas fa-align-left"></i> <span data-i18n="form.tabs.details">Details</span></button>
                    <button type="button" class="ftab-btn" data-ftab="salary"><i class="fas fa-dollar-sign"></i> <span data-i18n="form.tabs.salary">Salary</span></button>
                    <button type="button" class="ftab-btn" data-ftab="location"><i class="fas fa-map-marker-alt"></i> <span data-i18n="form.tabs.location">Location</span></button>
                    <button type="button" class="ftab-btn" data-ftab="application"><i class="fas fa-file-signature"></i> <span data-i18n="form.tabs.application">Application</span></button>
                    <button type="button" class="ftab-btn" data-ftab="skills"><i class="fas fa-tools"></i> <span data-i18n="form.tabs.skills">Skills</span></button>
                    <button type="button" class="ftab-btn" data-ftab="translations"><i class="fas fa-language"></i> <span data-i18n="form.tabs.translations">Translations</span></button>
                </div>

                <!-- TAB: Basic Info -->
                <div class="fpanel active" id="ftab-basic">
                    <div class="form-row">
                        <div class="form-group col-8">
                            <label class="required" data-i18n="form.fields.job_title.label">Job Title</label>
                            <input type="text" id="jobTitle" name="job_title" class="form-control" required placeholder="<?= __t('form.fields.job_title.placeholder','Enter job title') ?>">
                        </div>
                        <div class="form-group col-4">
                            <label data-i18n="form.fields.slug.label">Slug</label>
                            <input type="text" id="jobSlug" name="slug" class="form-control" placeholder="auto-generated">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-4">
                            <label class="required" data-i18n="form.fields.job_type.label">Job Type</label>
                            <select id="jobType" name="job_type" class="form-control" required>
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="temporary">Temporary</option>
                                <option value="internship">Internship</option>
                                <option value="freelance">Freelance</option>
                                <option value="remote">Remote</option>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <label data-i18n="form.fields.employment_type.label">Employment Type</label>
                            <select id="jobEmploymentType" name="employment_type" class="form-control">
                                <option value="permanent">Permanent</option>
                                <option value="temporary">Temporary</option>
                                <option value="seasonal">Seasonal</option>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <label class="required" data-i18n="form.fields.experience_level.label">Experience Level</label>
                            <select id="jobExperienceLevel" name="experience_level" class="form-control" required>
                                <option value="entry">Entry</option>
                                <option value="junior">Junior</option>
                                <option value="mid">Mid</option>
                                <option value="senior">Senior</option>
                                <option value="executive">Executive</option>
                                <option value="director">Director</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-4">
                            <label data-i18n="form.fields.category.label">Category</label>
                            <select id="jobCategory" name="category" class="form-control">
                                <option value="">-- Select --</option>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <label data-i18n="form.fields.department.label">Department</label>
                            <input type="text" id="jobDepartment" name="department" class="form-control" placeholder="e.g. Engineering">
                        </div>
                        <div class="form-group col-4">
                            <label data-i18n="form.fields.positions_available.label">Positions Available</label>
                            <input type="number" id="jobPositions" name="positions_available" class="form-control" value="1" min="1">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-4">
                            <label data-i18n="form.fields.status.label">Status</label>
                            <select id="jobStatus" name="status" class="form-control">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="closed">Closed</option>
                                <option value="filled">Filled</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <label data-i18n="form.fields.start_date.label">Start Date</label>
                            <input type="date" id="jobStartDate" name="start_date" class="form-control">
                        </div>
                        <div class="form-group col-4">
                            <label data-i18n="form.fields.entity.label">Entity ID</label>
                            <input type="number" id="jobEntityIdShow" class="form-control" placeholder="Entity ID">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="jobIsFeatured" name="is_featured" value="1">
                                <span data-i18n="form.fields.is_featured.label">Featured Job</span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="jobIsUrgent" name="is_urgent" value="1">
                                <span data-i18n="form.fields.is_urgent.label">Urgent Job</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- TAB: Details (translations input for primary lang) -->
                <div class="fpanel" id="ftab-details" style="display:none">
                    <p class="form-hint" data-i18n="form.details_hint">These fields are the primary language content. Add more languages in the Translations tab.</p>
                    <div class="form-group">
                        <label class="required" data-i18n="form.translations.description">Description</label>
                        <textarea id="jobDescription" name="description" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="form-group">
                        <label data-i18n="form.translations.requirements">Requirements</label>
                        <textarea id="jobRequirements" name="requirements" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label data-i18n="form.translations.responsibilities">Responsibilities</label>
                        <textarea id="jobResponsibilities" name="responsibilities" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label data-i18n="form.translations.benefits">Benefits</label>
                        <textarea id="jobBenefits" name="benefits" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <!-- TAB: Salary -->
                <div class="fpanel" id="ftab-salary" style="display:none">
                    <div class="form-row">
                        <div class="form-group col-4">
                            <label data-i18n="form.fields.salary_min.label">Min Salary</label>
                            <input type="number" id="jobSalaryMin" name="salary_min" class="form-control" placeholder="0.00" step="0.01">
                        </div>
                        <div class="form-group col-4">
                            <label data-i18n="form.fields.salary_max.label">Max Salary</label>
                            <input type="number" id="jobSalaryMax" name="salary_max" class="form-control" placeholder="0.00" step="0.01">
                        </div>
                        <div class="form-group col-4">
                            <label data-i18n="form.fields.salary_currency.label">Currency</label>
                            <select id="jobSalaryCurrency" name="salary_currency" class="form-control">
                                <option value="SAR">SAR</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label data-i18n="form.fields.salary_period.label">Salary Period</label>
                            <select id="jobSalaryPeriod" name="salary_period" class="form-control">
                                <option value="hourly">Hourly</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="form-group col-6">
                            <label class="checkbox-label" style="margin-top:2rem">
                                <input type="checkbox" id="jobSalaryNegotiable" name="salary_negotiable" value="1">
                                <span data-i18n="form.fields.salary_negotiable.label">Negotiable</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- TAB: Location -->
                <div class="fpanel" id="ftab-location" style="display:none">
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label data-i18n="form.fields.country_id.label">Country</label>
                            <select id="jobCountryId" name="country_id" class="form-control">
                                <option value="">-- Select Country --</option>
                            </select>
                        </div>
                        <div class="form-group col-6">
                            <label data-i18n="form.fields.city_id.label">City</label>
                            <select id="jobCityId" name="city_id" class="form-control">
                                <option value="">-- Select City --</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-8">
                            <label data-i18n="form.fields.work_location.label">Work Location Address</label>
                            <input type="text" id="jobWorkLocation" name="work_location" class="form-control" placeholder="Building, Street...">
                        </div>
                        <div class="form-group col-4">
                            <label class="checkbox-label" style="margin-top:2rem">
                                <input type="checkbox" id="jobIsRemote" name="is_remote" value="1">
                                <span data-i18n="form.fields.is_remote.label">Remote Work</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- TAB: Application -->
                <div class="fpanel" id="ftab-application" style="display:none">
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label data-i18n="form.fields.application_form_type.label">Application Form Type</label>
                            <select id="jobApplicationFormType" name="application_form_type" class="form-control">
                                <option value="simple">Simple</option>
                                <option value="custom">Custom</option>
                                <option value="external">External URL</option>
                            </select>
                        </div>
                        <div class="form-group col-6" id="externalUrlGroup">
                            <label data-i18n="form.fields.external_application_url.label">External URL</label>
                            <input type="url" id="jobExternalUrl" name="external_application_url" class="form-control" placeholder="https://...">
                        </div>
                    </div>
                    <div class="form-group">
                        <label data-i18n="form.fields.application_deadline.label">Application Deadline</label>
                        <input type="datetime-local" id="jobApplicationDeadline" name="application_deadline" class="form-control">
                    </div>
                </div>

                <!-- TAB: Skills -->
                <div class="fpanel" id="ftab-skills" style="display:none">
                    <div class="skills-header">
                        <h4 data-i18n="form.skills.title">Required Skills</h4>
                        <button type="button" class="btn btn-sm btn-secondary" id="addSkillRow">
                            <i class="fas fa-plus"></i> <span data-i18n="form.skills.add_skill">Add Skill</span>
                        </button>
                    </div>
                    <div id="skillsList" class="skills-list">
                        <!-- rows injected by JS -->
                    </div>
                    <input type="hidden" id="jobSkillsData" name="skills_data">
                </div>

                <!-- TAB: Translations -->
                <div class="fpanel" id="ftab-translations" style="display:none">
                    <div class="translations-header">
                        <h4 data-i18n="form.translations.title">Translations</h4>
                        <div class="form-row" style="gap:8px;align-items:flex-end">
                            <select id="translationLanguage" class="form-control" style="min-width:160px">
                                <option value="">-- Select Language --</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-secondary" id="addTranslationBtn">
                                <i class="fas fa-plus"></i> <span data-i18n="form.translations.add_translation">Add</span>
                            </button>
                        </div>
                    </div>
                    <div id="translationsList" class="translations-list">
                        <!-- panels injected by JS -->
                    </div>
                    <input type="hidden" id="jobTranslationsData" name="translations_data">
                </div>

                <!-- Form Footer -->
                <div class="form-actions-footer">
                    <?php if ($canEdit || $canCreate): ?>
                    <button type="submit" id="jobSubmitBtn" class="btn btn-primary">
                        <i class="fas fa-save"></i> <span data-i18n="form.buttons.save">Save Job</span>
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline" id="jobCancelBtn" data-i18n="form.buttons.cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Jobs Filters -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label data-i18n="filters.search">Search</label>
                    <input type="text" id="jobsSearch" class="form-control" placeholder="Search jobs...">
                </div>
                <div class="filter-group">
                    <label data-i18n="filters.status">Status</label>
                    <select id="jobsStatusFilter" class="form-control">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="closed">Closed</option>
                        <option value="filled">Filled</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label data-i18n="filters.job_type">Job Type</label>
                    <select id="jobsTypeFilter" class="form-control">
                        <option value="">All</option>
                        <option value="full_time">Full Time</option>
                        <option value="part_time">Part Time</option>
                        <option value="contract">Contract</option>
                        <option value="temporary">Temporary</option>
                        <option value="internship">Internship</option>
                        <option value="freelance">Freelance</option>
                        <option value="remote">Remote</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label data-i18n="filters.experience_level">Experience</label>
                    <select id="jobsExpFilter" class="form-control">
                        <option value="">All</option>
                        <option value="entry">Entry</option>
                        <option value="junior">Junior</option>
                        <option value="mid">Mid</option>
                        <option value="senior">Senior</option>
                        <option value="executive">Executive</option>
                        <option value="director">Director</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label data-i18n="filters.category">Category</label>
                    <select id="jobsCatFilter" class="form-control">
                        <option value="">All</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="jobsApplyFilter" class="btn btn-secondary" data-i18n="filters.apply">Apply</button>
                    <button id="jobsResetFilter" class="btn btn-outline" data-i18n="filters.reset">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Jobs Table -->
    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title" data-i18n="jobs.title">Jobs</h3>
            <div class="card-actions">
                <?php if ($canCreate): ?>
                <button id="jobsAddBtn" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> <span data-i18n="jobs.add_new">Add Job</span>
                </button>
                <?php endif; ?>
                <button id="jobsExportBtn" class="btn btn-secondary btn-sm">
                    <i class="fas fa-file-excel"></i> <span data-i18n="table.actions.export">Export</span>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div id="jobsTableLoading" class="loading-state"><div class="spinner"></div><p data-i18n="jobs.loading">Loading...</p></div>
            <div id="jobsTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="jobsTable">
                        <thead><tr>
                            <th>ID</th>
                            <th data-i18n="table.headers.job_title">Title</th>
                            <th data-i18n="table.headers.job_type">Type</th>
                            <th data-i18n="table.headers.experience_level">Experience</th>
                            <th data-i18n="table.headers.category">Category</th>
                            <th data-i18n="table.headers.salary">Salary</th>
                            <th data-i18n="table.headers.applications">Apps</th>
                            <th data-i18n="table.headers.deadline">Deadline</th>
                            <th data-i18n="table.headers.status">Status</th>
                            <th data-i18n="table.headers.actions">Actions</th>
                        </tr></thead>
                        <tbody id="jobsTableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <span class="pagination-info" id="jobsPaginationInfo"></span>
                    <div id="jobsPagination" class="pagination"></div>
                </div>
            </div>
            <div id="jobsEmptyState" class="empty-state" style="display:none">
                <div class="empty-icon">💼</div>
                <h3 data-i18n="table.empty.title">No Jobs Found</h3>
                <p data-i18n="table.empty.message">Start by adding your first job posting</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="Workspace.jobs.showForm()">
                    <i class="fas fa-plus"></i> <span data-i18n="table.empty.add_first">Add First Job</span>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: APPLICATIONS
══════════════════════════════════════════ -->
<div id="applicationsTab" class="ws-panel" style="display:none">

    <!-- Application Edit Modal-style card -->
    <div id="appFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="appFormTitle">Application Details</h3>
            <button class="btn btn-sm btn-outline" id="appCloseForm"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="appForm">
                <input type="hidden" id="appId" name="id">
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Applicant</label>
                        <input type="text" id="appFullName" name="full_name" class="form-control">
                    </div>
                    <div class="form-group col-6">
                        <label>Email</label>
                        <input type="email" id="appEmail" name="email" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Phone</label>
                        <input type="text" id="appPhone" name="phone" class="form-control">
                    </div>
                    <div class="form-group col-6">
                        <label>Current Position</label>
                        <input type="text" id="appCurrentPosition" name="current_position" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-4">
                        <label>Years of Experience</label>
                        <input type="number" id="appYearsExp" name="years_of_experience" class="form-control" min="0">
                    </div>
                    <div class="form-group col-4">
                        <label>Expected Salary</label>
                        <input type="number" id="appExpectedSalary" name="expected_salary" class="form-control">
                    </div>
                    <div class="form-group col-4">
                        <label>Notice Period (days)</label>
                        <input type="number" id="appNoticePeriod" name="notice_period" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>LinkedIn URL</label>
                        <input type="url" id="appLinkedin" name="linkedin_url" class="form-control">
                    </div>
                    <div class="form-group col-6">
                        <label>Portfolio URL</label>
                        <input type="url" id="appPortfolio" name="portfolio_url" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-4">
                        <label data-i18n="applications.status">Status</label>
                        <select id="appStatus" name="status" class="form-control">
                            <option value="submitted">Submitted</option>
                            <option value="under_review">Under Review</option>
                            <option value="shortlisted">Shortlisted</option>
                            <option value="interview_scheduled">Interview Scheduled</option>
                            <option value="interviewed">Interviewed</option>
                            <option value="offered">Offered</option>
                            <option value="accepted">Accepted</option>
                            <option value="rejected">Rejected</option>
                            <option value="withdrawn">Withdrawn</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label>Rating (1-5)</label>
                        <input type="number" id="appRating" name="rating" class="form-control" min="1" max="5">
                    </div>
                    <div class="form-group col-4">
                        <label>CV File URL</label>
                        <input type="url" id="appCvUrl" name="cv_file_url" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Cover Letter</label>
                    <textarea id="appCoverLetter" name="cover_letter" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Notes (internal)</label>
                    <textarea id="appNotes" name="notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="btn btn-outline" id="appCancelForm">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" id="appsSearch" class="form-control" placeholder="Name, email...">
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select id="appsStatusFilter" class="form-control">
                        <option value="">All</option>
                        <option value="submitted">Submitted</option>
                        <option value="under_review">Under Review</option>
                        <option value="shortlisted">Shortlisted</option>
                        <option value="interview_scheduled">Interview Scheduled</option>
                        <option value="interviewed">Interviewed</option>
                        <option value="offered">Offered</option>
                        <option value="accepted">Accepted</option>
                        <option value="rejected">Rejected</option>
                        <option value="withdrawn">Withdrawn</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Job</label>
                    <select id="appsJobFilter" class="form-control"><option value="">All Jobs</option></select>
                </div>
                <div class="filter-actions">
                    <button id="appsApplyFilter" class="btn btn-secondary">Apply</button>
                    <button id="appsResetFilter" class="btn btn-outline">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title" data-i18n="workspace.tabs.applications">Applications</h3>
            <div class="card-actions">
                <button id="appsExportBtn" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Export</button>
            </div>
        </div>
        <div class="card-body">
            <div id="appsTableLoading" class="loading-state"><div class="spinner"></div><p>Loading...</p></div>
            <div id="appsTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="appsTable">
                        <thead><tr>
                            <th>ID</th>
                            <th>Job</th>
                            <th>Applicant</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Experience</th>
                            <th>Expected Salary</th>
                            <th>Status</th>
                            <th>Rating</th>
                            <th>Applied</th>
                            <th>Actions</th>
                        </tr></thead>
                        <tbody id="appsTableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <span class="pagination-info" id="appsPaginationInfo"></span>
                    <div id="appsPagination" class="pagination"></div>
                </div>
            </div>
            <div id="appsEmptyState" class="empty-state" style="display:none">
                <div class="empty-icon">📄</div>
                <h3>No Applications Found</h3>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: INTERVIEWS
══════════════════════════════════════════ -->
<div id="interviewsTab" class="ws-panel" style="display:none">

    <div id="interviewFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="interviewFormTitle">Interview Details</h3>
            <button class="btn btn-sm btn-outline" id="interviewCloseForm"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="interviewForm">
                <input type="hidden" id="interviewId" name="id">
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Application</label>
                        <select id="interviewAppId" name="application_id" class="form-control">
                            <option value="">-- Select Application --</option>
                        </select>
                    </div>
                    <div class="form-group col-6">
                        <label>Interview Type</label>
                        <select id="interviewType" name="interview_type" class="form-control">
                            <option value="phone">Phone</option>
                            <option value="video">Video</option>
                            <option value="in_person">In Person</option>
                            <option value="technical">Technical</option>
                            <option value="hr">HR</option>
                            <option value="final">Final</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Interview Date & Time</label>
                        <input type="datetime-local" id="interviewDate" name="interview_date" class="form-control" required>
                    </div>
                    <div class="form-group col-6">
                        <label>Duration (minutes)</label>
                        <input type="number" id="interviewDuration" name="interview_duration" class="form-control" value="60" min="15">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Location</label>
                        <input type="text" id="interviewLocation" name="location" class="form-control">
                    </div>
                    <div class="form-group col-6">
                        <label>Meeting Link</label>
                        <input type="url" id="interviewMeetingLink" name="meeting_link" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Interviewer Name</label>
                        <input type="text" id="interviewerName" name="interviewer_name" class="form-control">
                    </div>
                    <div class="form-group col-6">
                        <label>Interviewer Email</label>
                        <input type="email" id="interviewerEmail" name="interviewer_email" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-4">
                        <label>Status</label>
                        <select id="interviewStatus" name="status" class="form-control">
                            <option value="scheduled">Scheduled</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="rescheduled">Rescheduled</option>
                            <option value="no_show">No Show</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label>Rating (1-5)</label>
                        <input type="number" id="interviewRating" name="rating" class="form-control" min="1" max="5">
                    </div>
                </div>
                <div class="form-group">
                    <label>Feedback</label>
                    <textarea id="interviewFeedback" name="feedback" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea id="interviewNotes" name="notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="btn btn-outline" id="interviewCancelForm">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" id="interviewsSearch" class="form-control" placeholder="Candidate or interviewer...">
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select id="interviewsStatusFilter" class="form-control">
                        <option value="">All</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="rescheduled">Rescheduled</option>
                        <option value="no_show">No Show</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Type</label>
                    <select id="interviewsTypeFilter" class="form-control">
                        <option value="">All</option>
                        <option value="phone">Phone</option>
                        <option value="video">Video</option>
                        <option value="in_person">In Person</option>
                        <option value="technical">Technical</option>
                        <option value="hr">HR</option>
                        <option value="final">Final</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="interviewsApplyFilter" class="btn btn-secondary">Apply</button>
                    <button id="interviewsResetFilter" class="btn btn-outline">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title" data-i18n="workspace.tabs.interviews">Interviews</h3>
            <div class="card-actions">
                <?php if ($canCreate): ?>
                <button id="interviewsAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Interview</button>
                <?php endif; ?>
                <button id="interviewsExportBtn" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Export</button>
            </div>
        </div>
        <div class="card-body">
            <div id="interviewsTableLoading" class="loading-state"><div class="spinner"></div><p>Loading...</p></div>
            <div id="interviewsTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr>
                            <th>ID</th>
                            <th>Applicant</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Duration</th>
                            <th>Interviewer</th>
                            <th>Status</th>
                            <th>Rating</th>
                            <th>Actions</th>
                        </tr></thead>
                        <tbody id="interviewsTableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <span class="pagination-info" id="interviewsPaginationInfo"></span>
                    <div id="interviewsPagination" class="pagination"></div>
                </div>
            </div>
            <div id="interviewsEmptyState" class="empty-state" style="display:none">
                <div class="empty-icon">📅</div><h3>No Interviews Found</h3>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: ALERTS
══════════════════════════════════════════ -->
<div id="alertsTab" class="ws-panel" style="display:none">

    <div id="alertFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="alertFormTitle">Alert Details</h3>
            <button class="btn btn-sm btn-outline" id="alertCloseForm"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="alertForm">
                <input type="hidden" id="alertId" name="id">
                <div class="form-row">
                    <div class="form-group col-8">
                        <label>Alert Name <span class="required-star">*</span></label>
                        <input type="text" id="alertName" name="alert_name" class="form-control" required>
                    </div>
                    <div class="form-group col-4">
                        <label>Frequency</label>
                        <select id="alertFrequency" name="frequency" class="form-control">
                            <option value="instant">Instant</option>
                            <option value="daily" selected>Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-12">
                        <label>Keywords</label>
                        <input type="text" id="alertKeywords" name="keywords" class="form-control" placeholder="e.g. PHP, Remote, Senior">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-4">
                        <label>Job Type</label>
                        <select id="alertJobType" name="job_type" class="form-control">
                            <option value="">Any</option>
                            <option value="full_time">Full Time</option>
                            <option value="part_time">Part Time</option>
                            <option value="remote">Remote</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label>Experience Level</label>
                        <select id="alertExpLevel" name="experience_level" class="form-control">
                            <option value="">Any</option>
                            <option value="entry">Entry</option>
                            <option value="junior">Junior</option>
                            <option value="mid">Mid</option>
                            <option value="senior">Senior</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label>Min Salary</label>
                        <input type="number" id="alertSalaryMin" name="salary_min" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Country</label>
                        <select id="alertCountryId" name="country_id" class="form-control">
                            <option value="">Any</option>
                        </select>
                    </div>
                    <div class="form-group col-6">
                        <label>City</label>
                        <select id="alertCityId" name="city_id" class="form-control">
                            <option value="">Any</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="alertIsActive" name="is_active" value="1" checked>
                        <span>Active</span>
                    </label>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="btn btn-outline" id="alertCancelForm">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" id="alertsSearch" class="form-control" placeholder="Alert name...">
                </div>
                <div class="filter-group">
                    <label>Active</label>
                    <select id="alertsActiveFilter" class="form-control">
                        <option value="">All</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Frequency</label>
                    <select id="alertsFreqFilter" class="form-control">
                        <option value="">All</option>
                        <option value="instant">Instant</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="alertsApplyFilter" class="btn btn-secondary">Apply</button>
                    <button id="alertsResetFilter" class="btn btn-outline">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title" data-i18n="workspace.tabs.alerts">Job Alerts</h3>
            <div class="card-actions">
                <button id="alertsAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Alert</button>
                <button id="alertsExportBtn" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Export</button>
            </div>
        </div>
        <div class="card-body">
            <div id="alertsTableLoading" class="loading-state"><div class="spinner"></div><p>Loading...</p></div>
            <div id="alertsTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Keywords</th>
                            <th>Job Type</th>
                            <th>Frequency</th>
                            <th>Active</th>
                            <th>Last Sent</th>
                            <th>Actions</th>
                        </tr></thead>
                        <tbody id="alertsTableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <span class="pagination-info" id="alertsPaginationInfo"></span>
                    <div id="alertsPagination" class="pagination"></div>
                </div>
            </div>
            <div id="alertsEmptyState" class="empty-state" style="display:none">
                <div class="empty-icon">🔔</div><h3>No Alerts Found</h3>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: QUESTIONS
══════════════════════════════════════════ -->
<div id="questionsTab" class="ws-panel" style="display:none">

    <div id="questionFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="questionFormTitle">Question Details</h3>
            <button class="btn btn-sm btn-outline" id="questionCloseForm"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="questionForm">
                <input type="hidden" id="questionId" name="id">
                <div class="form-row">
                    <div class="form-group col-8">
                        <label>Job <span class="required-star">*</span></label>
                        <select id="questionJobId" name="job_id" class="form-control" required>
                            <option value="">-- Select Job --</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label>Question Type</label>
                        <select id="questionType" name="question_type" class="form-control">
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="select">Select</option>
                            <option value="multiselect">Multi-select</option>
                            <option value="radio">Radio</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="file">File</option>
                            <option value="date">Date</option>
                            <option value="number">Number</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Question Text <span class="required-star">*</span></label>
                    <textarea id="questionText" name="question_text" class="form-control" rows="2" required></textarea>
                </div>
                <div class="form-group" id="questionOptionsGroup">
                    <label>Options <small>(comma-separated or JSON array)</small></label>
                    <textarea id="questionOptions" name="options" class="form-control" rows="2" placeholder='Yes,No,Maybe  or  ["Option 1","Option 2"]'></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Sort Order</label>
                        <input type="number" id="questionSortOrder" name="sort_order" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group col-6">
                        <label class="checkbox-label" style="margin-top:1.8rem">
                            <input type="checkbox" id="questionIsRequired" name="is_required" value="1">
                            <span>Required</span>
                        </label>
                    </div>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="btn btn-outline" id="questionCancelForm">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" id="questionsSearch" class="form-control" placeholder="Search questions...">
                </div>
                <div class="filter-group">
                    <label>Job</label>
                    <select id="questionsJobFilter" class="form-control"><option value="">All Jobs</option></select>
                </div>
                <div class="filter-group">
                    <label>Type</label>
                    <select id="questionsTypeFilter" class="form-control">
                        <option value="">All</option>
                        <option value="text">Text</option>
                        <option value="textarea">Textarea</option>
                        <option value="select">Select</option>
                        <option value="radio">Radio</option>
                        <option value="checkbox">Checkbox</option>
                        <option value="file">File</option>
                        <option value="number">Number</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="questionsApplyFilter" class="btn btn-secondary">Apply</button>
                    <button id="questionsResetFilter" class="btn btn-outline">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title" data-i18n="workspace.tabs.questions">Application Questions</h3>
            <div class="card-actions">
                <button id="questionsAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Question</button>
            </div>
        </div>
        <div class="card-body">
            <div id="questionsTableLoading" class="loading-state"><div class="spinner"></div><p>Loading...</p></div>
            <div id="questionsTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr>
                            <th>ID</th>
                            <th>Job</th>
                            <th>Question</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Sort</th>
                            <th>Actions</th>
                        </tr></thead>
                        <tbody id="questionsTableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <span class="pagination-info" id="questionsPaginationInfo"></span>
                    <div id="questionsPagination" class="pagination"></div>
                </div>
            </div>
            <div id="questionsEmptyState" class="empty-state" style="display:none">
                <div class="empty-icon">❓</div><h3>No Questions Found</h3>
            </div>
        </div>
    </div>
</div>

</div><!-- /page-container -->

<!-- Global config — same pattern as brands.php -->
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE      = window.APP_CONFIG.API_BASE  || '/api';
window.APP_CONFIG.TENANT_ID     = window.APP_CONFIG.TENANT_ID || <?= $tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN    = window.APP_CONFIG.CSRF_TOKEN|| '<?= addslashes($csrf) ?>';
window.APP_CONFIG.USER_ID       = window.APP_CONFIG.USER_ID   || <?= $userId ?>;
window.USER_LANGUAGE            = window.USER_LANGUAGE        || '<?= addslashes($lang) ?>';
window.USER_DIRECTION           = window.USER_DIRECTION       || '<?= addslashes($dir)  ?>';
window.CSRF_TOKEN               = window.CSRF_TOKEN           || '<?= addslashes($csrf) ?>';

window.JOBS_CONFIG = {
    lang:           '<?= addslashes($lang) ?>',
    dir:            '<?= addslashes($dir) ?>',
    tenantId:       <?= $tenantId ?>,
    userId:         <?= $userId ?>,
    csrfToken:      '<?= addslashes($csrf) ?>',
    isFragment:     <?= $isFragment ? 'true' : 'false' ?>,
    jobsApi:        '/api/jobs',
    applicationsApi:'/api/job_applications',
    interviewsApi:  '/api/job_interviews',
    alertsApi:      '/api/job_alerts',
    questionsApi:   '/api/job_application_questions',
    skillsApi:      '/api/job_skills',
    languagesApi:   '/api/languages',
    categoriesApi:  '/api/job_categories',
    countriesApi:   '/api/countries',
    citiesApi:      '/api/cities',
    currenciesApi:  '/api/currencies'
};

window.PAGE_PERMISSIONS = <?= json_encode([
    'canCreate'    => $canCreate,
    'canEdit'      => $canEdit,
    'canDelete'    => $canDelete,
    'canView'      => $canView,
    'isSuperAdmin' => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/jobs.js?v=<?= time() ?>"></script>
<script>
(function () {
    let attempts = 0, max = 80;
    const iv = setInterval(function () {
        attempts++;
        if (window.AdminFramework && window.Jobs && typeof window.Jobs.init === 'function') {
            clearInterval(iv);
            console.log('[Jobs] Framework ready – initializing...');
            const p = window.Jobs.init();
            if (p && typeof p.then === 'function') {
                p.then(function () { console.log('[Jobs] init() done'); })
                 .catch(function (e) { console.error('[Jobs] init() error', e); });
            }
        } else if (attempts > max) {
            clearInterval(iv);
            console.error('[Jobs] Timeout waiting for AdminFramework / Jobs module.');
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/jobs.js?v=<?= time() ?>"></script>
<?php endif; ?>

<?php if (!$isFragment): require_once __DIR__ . '/../includes/footer.php'; endif; ?>