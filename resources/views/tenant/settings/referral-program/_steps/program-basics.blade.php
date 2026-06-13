<div class="space-y-4">
    <div>
        <label class="form-label">Program Name <span class="text-red-500">*</span></label>
        <input type="text" x-model="data.program_name" maxlength="150" class="form-input" placeholder="e.g. Acme Referral Rewards">
        <p x-show="errors.program_name" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.program_name?.[0]"></p>
    </div>

    <div>
        <label class="form-label">Brand Display Name</label>
        <input type="text" x-model="data.brand_display_name" maxlength="150" class="form-input" placeholder="What referrers and customers will see">
        <p class="text-xs text-gray-400 mt-1">Defaults to your workspace name if left blank.</p>
    </div>

    <div>
        <label class="form-label">Program Description</label>
        <textarea x-model="data.program_description" rows="3" maxlength="1000" class="form-input" placeholder="Briefly describe what this referral program is for"></textarea>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="form-label">Currency <span class="text-red-500">*</span></label>
            <select x-model="data.currency" class="form-input">
                <option value="PHP">PHP — Philippine Peso</option>
                <option value="USD">USD — US Dollar</option>
                <option value="EUR">EUR — Euro</option>
                <option value="GBP">GBP — British Pound</option>
                <option value="SGD">SGD — Singapore Dollar</option>
                <option value="AUD">AUD — Australian Dollar</option>
                <option value="CAD">CAD — Canadian Dollar</option>
                <option value="JPY">JPY — Japanese Yen</option>
            </select>
            <p x-show="errors.currency" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.currency?.[0]"></p>
        </div>
        <div>
            <label class="form-label">Timezone <span class="text-red-500">*</span></label>
            <select x-model="data.timezone" class="form-input">
                <option value="Asia/Manila">Asia/Manila</option>
                <option value="Asia/Singapore">Asia/Singapore</option>
                <option value="Asia/Hong_Kong">Asia/Hong Kong</option>
                <option value="Asia/Tokyo">Asia/Tokyo</option>
                <option value="Australia/Sydney">Australia/Sydney</option>
                <option value="Europe/London">Europe/London</option>
                <option value="America/New_York">America/New York</option>
                <option value="America/Los_Angeles">America/Los Angeles</option>
                <option value="UTC">UTC</option>
            </select>
            <p x-show="errors.timezone" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.timezone?.[0]"></p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="form-label">Support Email</label>
            <input type="email" x-model="data.support_email" maxlength="150" class="form-input" placeholder="support@yourcompany.com">
            <p x-show="errors.support_email" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.support_email?.[0]"></p>
        </div>
        <div>
            <label class="form-label">Program Owner</label>
            <input type="text" x-model="data.program_owner" maxlength="150" class="form-input" placeholder="Who manages this program?">
        </div>
    </div>
</div>
