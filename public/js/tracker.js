/**
 * D&D Initiative Tracker - Live Combatant & Tracker Interactions
 */
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const encounterId = document.querySelector('[data-encounter-id]')?.getAttribute('data-encounter-id');

    /** Helper for JSON fetch requests */
    async function postJson(url, data = {}) {
        const payload = { ...data, _csrf: csrfToken };
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.error || 'Server error: ' + res.status);
        }

        return res.json();
    }

    /** Flash feedback helper */
    function flashElement(el, type = 'success') {
        if (!el) return;
        el.classList.remove('flash-saved', 'flash-damage', 'flash-heal');
        void el.offsetWidth; // force reflow
        if (type === 'damage') {
            el.classList.add('flash-damage');
        } else if (type === 'heal') {
            el.classList.add('flash-heal');
        } else {
            el.classList.add('flash-saved');
        }
    }

    // 1. Inline editing of combatant properties (Name, AC, Max HP, Initiative, Notes, etc.)
    document.querySelectorAll('.combatant-row .inline-edit').forEach((input) => {
        let originalValue = input.value;

        const saveChanges = async () => {
            const row = input.closest('.combatant-row');
            if (!row) return;
            const pId = row.getAttribute('data-participant-id');
            const field = input.getAttribute('data-field');
            const value = input.value;

            if (value === originalValue) return;

            try {
                input.disabled = true;
                const res = await postJson(`/encounters/${encounterId}/participants/${pId}`, {
                    [field]: value,
                });
                originalValue = value;
                flashElement(input, 'success');

                // If Max HP was edited, update HP display and bar
                if (field === 'max_hp' && res.participant) {
                    updateHpDisplay(row, res.participant);
                }
            } catch (err) {
                console.error('Failed to save combatant field:', err);
                input.value = originalValue;
                alert('Could not save changes: ' + err.message);
            } finally {
                input.disabled = false;
            }
        };

        input.addEventListener('blur', saveChanges);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                input.blur();
            } else if (e.key === 'Escape') {
                input.value = originalValue;
                input.blur();
            }
        });
    });

    // 2. Quick HP Adjustments (Damage, Heal, Temp HP)
    document.querySelectorAll('.combatant-row').forEach((row) => {
        const pId = row.getAttribute('data-participant-id');
        const hpInput = row.querySelector('.hp-adjust-input');

        const adjustHp = async (action) => {
            const amount = parseInt(hpInput?.value, 10) || 1;
            if (amount <= 0) return;

            try {
                const res = await postJson(`/encounters/${encounterId}/participants/${pId}/hp`, {
                    action: action,
                    amount: amount,
                });

                if (res.success && res.participant) {
                    updateHpDisplay(row, res.participant);
                    flashElement(row.querySelector('.hp-box'), action === 'damage' ? 'damage' : 'heal');
                    if (hpInput) hpInput.value = '';
                }
            } catch (err) {
                console.error('Failed to adjust HP:', err);
                alert('Error adjusting HP: ' + err.message);
            }
        };

        row.querySelector('.btn-damage')?.addEventListener('click', () => adjustHp('damage'));
        row.querySelector('.btn-heal')?.addEventListener('click', () => adjustHp('heal'));
        row.querySelector('.btn-temp-hp')?.addEventListener('click', () => adjustHp('temp'));

        // Allow pressing Enter in hp adjustment input to apply damage
        hpInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                adjustHp('damage');
            }
        });
    });

    /** Update HP bar and numbers in DOM */
    function updateHpDisplay(row, p) {
        const curHp = parseInt(p.current_hp, 10);
        const maxHp = parseInt(p.max_hp, 10) || 1;
        const tempHp = parseInt(p.temp_hp, 10) || 0;

        const curInput = row.querySelector('.inline-edit[data-field="current_hp"]');
        const maxInput = row.querySelector('.inline-edit[data-field="max_hp"]');
        const tempInput = row.querySelector('.inline-edit[data-field="temp_hp"]');
        const hpBar = row.querySelector('.hp-bar-fill');
        const tempBar = row.querySelector('.hp-bar-temp');

        if (curInput) curInput.value = curHp;
        if (maxInput) maxInput.value = maxHp;
        if (tempInput) tempInput.value = tempHp;

        const percent = Math.max(0, Math.min(100, (curHp / maxHp) * 100));
        if (hpBar) {
            hpBar.style.width = percent + '%';
            if (percent <= 25) {
                hpBar.className = 'hp-bar-fill hp-critical';
            } else if (percent <= 50) {
                hpBar.className = 'hp-bar-fill hp-low';
            } else {
                hpBar.className = 'hp-bar-fill hp-healthy';
            }
        }

        if (tempBar) {
            const tempPercent = Math.max(0, Math.min(100, (tempHp / maxHp) * 100));
            tempBar.style.width = tempPercent + '%';
        }

        // Toggle downed appearance
        if (curHp === 0) {
            row.classList.add('is-down');
        } else {
            row.classList.remove('is-down');
        }
    }

    // 3. Roll single participant initiative
    document.querySelectorAll('.btn-roll-single-init').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const row = btn.closest('.combatant-row');
            const pId = row?.getAttribute('data-participant-id');
            if (!pId) return;

            try {
                btn.disabled = true;
                const res = await postJson(`/encounters/${encounterId}/participants/${pId}/roll-initiative`);
                if (res.success) {
                    const initInput = row.querySelector('.inline-edit[data-field="initiative"]');
                    if (initInput) {
                        initInput.value = res.initiative;
                        flashElement(initInput, 'success');
                    }
                }
            } catch (err) {
                console.error('Failed to roll initiative:', err);
            } finally {
                btn.disabled = false;
            }
        });
    });

    // 4. Condition Toggle
    document.querySelectorAll('.combatant-row').forEach((row) => {
        const pId = row.getAttribute('data-participant-id');

        // Condition select picker
        const condSelect = row.querySelector('.condition-select');
        condSelect?.addEventListener('change', async () => {
            const condId = condSelect.value;
            if (!condId) return;

            try {
                const res = await postJson(`/encounters/${encounterId}/participants/${pId}/condition`, {
                    condition_id: condId,
                });

                if (res.success) {
                    window.location.reload(); // Refresh to update conditions cleanly
                }
            } catch (err) {
                console.error('Error toggling condition:', err);
            }
        });

        // Condition pill remove buttons
        row.querySelectorAll('.btn-remove-condition').forEach((cBtn) => {
            cBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                const condId = cBtn.getAttribute('data-condition-id');
                try {
                    await postJson(`/encounters/${encounterId}/participants/${pId}/condition`, {
                        condition_id: condId,
                    });
                    cBtn.closest('.condition-badge')?.remove();
                } catch (err) {
                    console.error('Error removing condition:', err);
                }
            });
        });
    });

    // 5. Active toggle (alive/active vs down/removed)
    document.querySelectorAll('.toggle-active-checkbox').forEach((checkbox) => {
        checkbox.addEventListener('change', async () => {
            const row = checkbox.closest('.combatant-row');
            const pId = row?.getAttribute('data-participant-id');
            if (!pId) return;

            try {
                const res = await postJson(`/encounters/${encounterId}/participants/${pId}/toggle-active`);
                if (res.success) {
                    if (res.is_active) {
                        row.classList.remove('is-inactive');
                    } else {
                        row.classList.add('is-inactive');
                    }
                }
            } catch (err) {
                console.error('Error toggling active state:', err);
                checkbox.checked = !checkbox.checked;
            }
        });
    });

    // 6. Interactive Quick Dice Roller Widget
    const diceForm = document.getElementById('quick-dice-form');
    const diceInput = document.getElementById('quick-dice-input');
    const diceOutput = document.getElementById('dice-result-display');

    const rollExpression = async (expr) => {
        if (!expr) return;
        if (diceOutput) diceOutput.textContent = 'Rolling...';
        try {
            const res = await fetch(`/api/dice?dice=${encodeURIComponent(expr)}`);
            const data = await res.json();
            if (data.success && data.result) {
                if (diceOutput) {
                    diceOutput.innerHTML = `<strong>${data.result.total}</strong> <span class="breakdown">(${data.result.breakdown})</span>`;
                    flashElement(diceOutput, 'success');
                }
            } else {
                if (diceOutput) diceOutput.textContent = data.error || 'Invalid roll';
            }
        } catch (err) {
            if (diceOutput) diceOutput.textContent = 'Roll failed.';
        }
    };

    diceForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        rollExpression(diceInput?.value || '1d20');
    });

    document.querySelectorAll('.btn-quick-die').forEach((dieBtn) => {
        dieBtn.addEventListener('click', () => {
            const expr = dieBtn.getAttribute('data-die');
            if (diceInput) diceInput.value = expr;
            rollExpression(expr);
        });
    });

    // 7. Tabs in Add Combatants Panel
    document.querySelectorAll('.tab-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const tabTarget = btn.getAttribute('data-tab');
            document.querySelectorAll('.tab-btn').forEach((b) => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach((c) => c.classList.remove('active'));

            btn.classList.add('active');
            document.getElementById(tabTarget)?.classList.add('active');
        });
    });
});
