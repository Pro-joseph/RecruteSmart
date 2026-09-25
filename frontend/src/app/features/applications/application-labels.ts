export const STATUS_LABELS: Record<string, string> = {
  new: 'Nouveau',
  shortlisted: 'Présélectionné',
  interview: 'Entretien',
  offer: 'Proposition',
  hired: 'Recruté',
  rejected: 'Refusé',
};

export const STATUS_BADGES: Record<string, string> = {
  new: 'badge-blue',
  shortlisted: 'badge-indigo',
  interview: 'badge-teal',
  offer: 'badge-amber',
  hired: 'badge-green',
  rejected: 'badge-red',
};

export const VERDICT_LABELS: Record<string, string> = {
  compliant: 'Conforme',
  improvable: 'À améliorer',
  non_compliant: 'Non conforme',
};

export const VERDICT_BADGES: Record<string, string> = {
  compliant: 'badge-green',
  improvable: 'badge-amber',
  non_compliant: 'badge-red',
};

export const STATUSES = Object.keys(STATUS_LABELS);

export const VERDICTS = Object.keys(VERDICT_LABELS);
