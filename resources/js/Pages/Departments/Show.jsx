import LookupShow from '../../Components/Staffs/LookupShow';

export default function DepartmentsShow({ item, staff }) {
    return <LookupShow noun="department" baseUrl="/departments" listLabel="departments" otherLabel="Designation" item={item} staff={staff} />;
}