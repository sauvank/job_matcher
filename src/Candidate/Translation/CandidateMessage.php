<?php

declare(strict_types=1);

namespace App\Candidate\Translation;

final class CandidateMessage
{
    public const INVALID_SKILL_NAME = 'candidate.cv.analysis.invalid_skill_name';
    public const INVALID_CONFIDENCE = 'candidate.cv.analysis.invalid_confidence';
    public const INVALID_SKILL = 'candidate.cv.analysis.invalid_skill';
    public const INVALID_SKILL_METADATA = 'candidate.cv.analysis.invalid_skill_metadata';
    public const INVALID_ANALYSIS = 'candidate.cv.analysis.invalid_analysis';
    public const INVALID_WARNING = 'candidate.cv.analysis.invalid_warning';
    public const INVALID_TEXT = 'candidate.cv.analysis.invalid_text';
    public const UNKNOWN_ANALYZER = 'candidate.cv.analysis.unknown_analyzer';
    public const MISSING_OPENAI_KEY = 'candidate.cv.analysis.missing_openai_key';
    public const INVALID_OPENAI_JSON = 'candidate.cv.analysis.invalid_openai_json';
    public const EMPTY_OPENAI_ANALYSIS = 'candidate.cv.analysis.empty_openai_analysis';
    public const MISSING_OPENAI_OUTPUT = 'candidate.cv.analysis.missing_openai_output';
    public const OPENAI_AUTHENTICATION_FAILED = 'candidate.cv.analysis.openai_authentication_failed';
    public const OPENAI_QUOTA_EXCEEDED = 'candidate.cv.analysis.openai_quota_exceeded';
    public const OPENAI_RATE_LIMITED = 'candidate.cv.analysis.openai_rate_limited';
    public const OPENAI_REQUEST_FAILED = 'candidate.cv.analysis.openai_request_failed';
    public const OPENAI_UNAVAILABLE = 'candidate.cv.analysis.openai_unavailable';
    public const OPENAI_TIMEOUT = 'candidate.cv.analysis.openai_timeout';
    public const OPENAI_CONNECTION_FAILED = 'candidate.cv.analysis.openai_connection_failed';
    public const FILE_TYPE_NOT_ALLOWED = 'candidate.cv.upload.file_type_not_allowed';
    public const FILE_UNREADABLE = 'candidate.cv.upload.file_unreadable';
    public const FILE_NOT_FOUND = 'candidate.cv.extraction.file_not_found';
    public const FORMAT_NOT_SUPPORTED = 'candidate.cv.extraction.format_not_supported';
    public const TEXT_TOO_SHORT = 'candidate.cv.extraction.text_too_short';
    public const PDF_EXTRACTION_FAILED = 'candidate.cv.extraction.pdf_failed';
    public const DOCX_EXTRACTION_FAILED = 'candidate.cv.extraction.docx_failed';
    public const ANALYSIS_NOT_APPLICABLE = 'candidate.cv.analysis.not_applicable';
    public const DOCUMENT_NOT_FOUND = 'candidate.cv.document_not_found';
    public const PROCESS_ALREADY_RUNNING = 'candidate.cv.process.already_running';
    public const PROCESS_COMPLETED = 'candidate.cv.process.completed';
    public const AI_PROCESS_FAILED = 'candidate.cv.process.ai_failed';
    public const UPLOAD_REQUIRED = 'candidate.cv.upload.required';
    public const UPLOAD_TOO_LARGE = 'candidate.cv.upload.too_large';
    public const UPLOAD_INVALID_TYPE = 'candidate.cv.upload.invalid_type';
    public const UPLOAD_ACCEPTED = 'candidate.cv.upload.accepted';
    public const UPLOAD_DUPLICATE = 'candidate.cv.upload.duplicate';
    public const ANALYSIS_APPLIED = 'candidate.cv.analysis.applied';
    public const REANALYSIS_ACCEPTED = 'candidate.cv.analysis.reanalysis_accepted';
    public const DOCUMENT_DELETED = 'candidate.cv.deleted';
    public const DOCUMENT_DELETE_PROCESSING = 'candidate.cv.delete_processing';

    private function __construct()
    {
    }
}
