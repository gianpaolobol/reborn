# MVP Release Criteria

## Functional criteria

The MVP can be released for a controlled pilot when:

- users can register/login;
- repair cases can be created and submitted;
- photos can be uploaded;
- admin can classify cases;
- user can view diagnosis summary;
- repair paths can be selected;
- provider quote flow works;
- maker/model/bounty path exists at metadata level;
- user can record outcome;
- knowledge signals are stored;
- admin can view core metrics.

## UX criteria

- every main flow has loading, empty and error states;
- low confidence is clearly communicated;
- restricted/risky cases are not treated as normal;
- user never needs to know the term STL to start;
- repair journey is more prominent than marketplace browsing.

## Security criteria

- passwords are hashed;
- sessions are protected;
- upload file types and size are validated;
- admin routes are role-protected;
- provider/maker queues are role-protected;
- unsafe categories can be blocked;
- basic audit/event log exists.

## Data criteria

- every case has a stable id and public reference;
- every submitted case has a Repair DNA draft;
- outcome feedback is preserved;
- failed repairs are recorded;
- graph-ready signals are created.

## Business criteria

- at least one provider can quote a real case;
- at least one maker can submit model metadata;
- at least one successful or failed real repair can be recorded;
- the platform can explain how the next repair becomes easier.
