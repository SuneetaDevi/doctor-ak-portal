/**
 * Tests for assets/js/doctor-ak-symptom-match.js (matching, follow-up
 * question flow and safety-rule triggering). No dependencies:
 *
 *     node --test "tests/js/*.test.js"
 *
 * All content below is synthetic test data, not clinical guidance.
 */
'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const Match = require( '../../assets/js/doctor-ak-symptom-match.js' );

const MAPPINGS = [
	{
		id: 1,
		name: 'Headache',
		synonyms: [ 'head pain', 'sar dard' ],
		specialties: [ 'neurologist' ],
		questions: [
			{ id: 'onset', text: 'Sudden?', type: 'yesno', required: true, affects: { safety: true }, options: [ { id: 'yes', label: 'Yes' }, { id: 'no', label: 'No' } ] },
			{ id: 'where', text: 'Where?', type: 'single', required: false, affects: { matching: true }, options: [ { id: 'eye', label: 'Around the eye', specialties: [ 'ophthalmologist' ] }, { id: 'other', label: 'Elsewhere', specialties: [] } ], show_if: { question: 'onset', answers: [ 'no' ] } },
			{ id: 'days', text: 'How many days?', type: 'number', required: false, affects: {}, number: { min: 0, max: 365, unit: 'days' } },
		],
	},
	{ id: 2, name: 'Stomach pain', synonyms: [ 'pet dard', 'tummy pain' ], specialties: [ 'gastroenterologist' ], questions: [] },
	{ id: 3, name: 'Fever', synonyms: [ 'bukhar' ], specialties: [ 'general-physician' ], questions: [
		{ id: 'with', text: 'Also?', type: 'multi', required: true, affects: { safety: true }, options: [ { id: 'rash', label: 'Rash' }, { id: 'stiff', label: 'Stiff neck' }, { id: 'none', label: 'None of these' } ] },
	] },
];

const RULES = [
	{ id: 10, level: 'urgent', title: 'Long headache', interrupt: false, triggers: [ { symptom: 1, question: 'days', answers: [], min: 7, max: null } ] },
	{ id: 11, level: 'emergency', title: 'Sudden headache', interrupt: true, triggers: [ { symptom: 1, question: 'onset', answers: [ 'yes' ] } ] },
	{ id: 12, level: 'emergency', title: 'Fever and stiff neck', interrupt: true, triggers: [ { symptom: 3, question: 'with', answers: [ 'stiff' ] } ] },
	{ id: 13, level: 'urgent', title: 'Stomach pain chosen', interrupt: false, triggers: [ { symptom: 2, question: '' } ] },
];

const m = Match.create( MAPPINGS );

test( 'exact names and phrases are recognised, ignoring case, plurals and filler', () => {
	const r = m.analyse( 'I have a bad HEADACHES since 2 days, pet dard aur bukhar' );
	assert.deepEqual( r.recognized.sort(), [ 1, 2, 3 ] );
	assert.equal( r.ambiguous.length, 0 );
} );

test( 'Roman Urdu phrases are matched', () => {
	assert.deepEqual( m.analyse( 'mujhe sar dard ho raha hai' ).recognized, [ 1 ] );
} );

test( 'partial or misspelt phrases are never accepted silently', () => {
	const r = m.analyse( 'stomache' );
	assert.deepEqual( r.recognized, [] );
	assert.equal( r.ambiguous.length, 1 );
	assert.ok( r.ambiguous[ 0 ].candidates.includes( 2 ) );
} );

test( 'unrelated text is unmatched, not mapped to a specialty', () => {
	const r = m.analyse( 'feeling purple' );
	assert.deepEqual( r.recognized, [] );
	assert.deepEqual( r.unknown, [ 'feeling purple' ] );
	assert.deepEqual( m.specialtiesFor( r.recognized ), [] );
} );

test( 'suggestions include synonyms and say which phrase matched', () => {
	const s = m.suggest( 'tummy' );
	assert.equal( s[ 0 ].id, 2 );
	assert.equal( s[ 0 ].via, 'tummy pain' );
} );

test( 'questions are asked in order; show_if hides until the parent answer matches', () => {
	assert.deepEqual( m.visibleQuestions( [ 1 ], {} ).map( ( q ) => q.key ), [ '1:onset', '1:days' ] );
	assert.deepEqual( m.visibleQuestions( [ 1 ], { '1:onset': 'yes' } ).map( ( q ) => q.key ), [ '1:onset', '1:days' ] );
	assert.deepEqual( m.visibleQuestions( [ 1 ], { '1:onset': 'no' } ).map( ( q ) => q.key ), [ '1:onset', '1:where', '1:days' ] );
} );

test( 'questions of several symptoms are combined in selection order', () => {
	assert.deepEqual( m.visibleQuestions( [ 3, 1 ], {} ).map( ( q ) => q.key ), [ '3:with', '1:onset', '1:days' ] );
	assert.deepEqual( m.visibleQuestions( [ 2 ], {} ), [] );
} );

test( 'isAnswered validates choices and number ranges', () => {
	const q = MAPPINGS[ 0 ].questions;
	assert.equal( Match.isAnswered( q[ 0 ], 'yes' ), true );
	assert.equal( Match.isAnswered( q[ 0 ], 'maybe' ), false );
	assert.equal( Match.isAnswered( q[ 2 ], 3 ), true );
	assert.equal( Match.isAnswered( q[ 2 ], -1 ), false );
	assert.equal( Match.isAnswered( q[ 2 ], 400 ), false );
	assert.equal( Match.isAnswered( q[ 2 ], '3' ), false );
	assert.equal( Match.isAnswered( MAPPINGS[ 2 ].questions[ 0 ], [] ), false );
	assert.equal( Match.isAnswered( MAPPINGS[ 2 ].questions[ 0 ], [ 'rash', 'stiff' ] ), true );
	assert.equal( Match.isAnswered( MAPPINGS[ 2 ].questions[ 0 ], [ 'rash', 'bogus' ] ), false );
} );

test( 'safety rules fire only on the configured answers', () => {
	assert.deepEqual( m.triggeredRules( RULES, [ 1 ], {} ).map( ( r ) => r.id ), [] );
	assert.deepEqual( m.triggeredRules( RULES, [ 1 ], { '1:onset': 'no' } ).map( ( r ) => r.id ), [] );
	assert.deepEqual( m.triggeredRules( RULES, [ 1 ], { '1:onset': 'yes' } ).map( ( r ) => r.id ), [ 11 ] );
} );

test( 'number triggers use the clinician-entered range only', () => {
	assert.deepEqual( m.triggeredRules( RULES, [ 1 ], { '1:days': 6 } ).map( ( r ) => r.id ), [] );
	assert.deepEqual( m.triggeredRules( RULES, [ 1 ], { '1:days': 7 } ).map( ( r ) => r.id ), [ 10 ] );
	assert.deepEqual( m.triggeredRules( RULES, [ 1 ], { '1:days': 9999 } ).map( ( r ) => r.id ), [], 'out-of-range input is not an answer' );
} );

test( 'multi-answer triggers fire when any listed answer is chosen', () => {
	assert.deepEqual( m.triggeredRules( RULES, [ 3 ], { '3:with': [ 'rash' ] } ).map( ( r ) => r.id ), [] );
	assert.deepEqual( m.triggeredRules( RULES, [ 3 ], { '3:with': [ 'rash', 'stiff' ] } ).map( ( r ) => r.id ), [ 12 ] );
} );

test( 'symptom-only triggers fire as soon as the symptom is chosen', () => {
	assert.deepEqual( m.triggeredRules( RULES, [ 2 ], {} ).map( ( r ) => r.id ), [ 13 ] );
} );

test( 'rules for symptoms that are not selected never fire', () => {
	assert.deepEqual( m.triggeredRules( RULES, [ 2 ], { '1:onset': 'yes' } ).map( ( r ) => r.id ), [ 13 ] );
} );

test( 'emergency rules come before urgent ones', () => {
	const ids = m.triggeredRules( RULES, [ 1, 2 ], { '1:onset': 'yes', '1:days': 10 } ).map( ( r ) => r.id );
	assert.deepEqual( ids, [ 11, 10, 13 ] );
} );

test( 'answers to hidden questions do not count', () => {
	const rules = [ { id: 20, level: 'urgent', title: 'Eye', triggers: [ { symptom: 1, question: 'where', answers: [ 'eye' ] } ] } ];
	assert.deepEqual( m.triggeredRules( rules, [ 1 ], { '1:onset': 'yes', '1:where': 'eye' } ), [] );
	assert.equal( m.triggeredRules( rules, [ 1 ], { '1:onset': 'no', '1:where': 'eye' } ).length, 1 );
	assert.deepEqual( m.specialtiesFor( [ 1 ], { '1:onset': 'yes', '1:where': 'eye' } ).map( ( s ) => s.slug ), [ 'neurologist' ] );
} );

test( 'answers add only the specialties a clinician linked to them', () => {
	const specs = m.specialtiesFor( [ 1 ], { '1:onset': 'no', '1:where': 'eye' } );
	assert.deepEqual( specs.map( ( s ) => s.slug ), [ 'neurologist', 'ophthalmologist' ] );
	assert.deepEqual( specs[ 1 ].answers, [ 'Headache: Around the eye' ] );
	assert.deepEqual( m.specialtiesFor( [ 1 ], { '1:onset': 'no', '1:where': 'other' } ).map( ( s ) => s.slug ), [ 'neurologist' ] );
} );

test( 'without approved rules nothing is ever triggered', () => {
	assert.deepEqual( m.triggeredRules( [], [ 1, 2, 3 ], { '1:onset': 'yes' } ), [] );
} );

test( 'doctors ranked by specialty, then distance; location denial still lists doctors', () => {
	const doctors = [
		{ id: 1, name: 'Dr A', specialties: [ 'neurologist' ], clinics: [ { id: 1, lat: 24.9, lng: 67.1 } ], video: { offered: true } },
		{ id: 2, name: 'Dr B', specialties: [ 'neurologist' ], clinics: [ { id: 2, lat: 24.8, lng: 67.0 } ], video: { offered: false } },
		{ id: 3, name: 'Dr C', specialties: [ 'dermatologist' ], clinics: [ { id: 3, lat: 24.8, lng: 67.0 } ] },
	];
	const specs = m.specialtiesFor( [ 1 ] );
	const near = Match.rankDoctors( doctors, specs, { origin: { lat: 24.8, lng: 67.0 } } );
	assert.deepEqual( near.clinic.map( ( e ) => e.doctor.id ), [ 2, 1 ] );
	assert.equal( near.mode, 'distance' );
	const none = Match.rankDoctors( doctors, specs, {} );
	assert.equal( none.mode, 'none' );
	assert.deepEqual( none.clinic.map( ( e ) => e.doctor.id ).sort(), [ 1, 2 ] );
	assert.deepEqual( none.online.map( ( e ) => e.doctor.id ), [ 1 ] );
	assert.ok( none.clinic.every( ( e ) => null === e.distanceKm ), 'no distance claimed without a location' );
} );
