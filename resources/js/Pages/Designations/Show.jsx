import LookupShow from '../../Components/Staffs/LookupShow';

export default function DesignationsShow({ item, staff }) {
    return <LookupShow noun="designation" baseUrl="/designations" listLabel="designations" otherLabel="Department" item={item} staff={staff} />;
}